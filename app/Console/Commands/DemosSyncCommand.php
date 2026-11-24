<?php

namespace App\Console\Commands;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Premium\FeatureGrant;
use App\Models\User;
use App\Services\Events\EventGalleryService;
use App\Support\Demos\DemoCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates the demonstration events of the home page — or puts them back
 * as they were made, should anyone have changed them in the panel.
 */
#[Signature('demos:sync {--user= : E-mail do dono das demos (padrão: o primeiro admin)} {--images= : Pasta com as imagens (padrão: resources/demo)}')]
#[Description('Cria ou restaura os eventos de demonstração da página inicial')]
class DemosSyncCommand extends Command
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var array<int, string> */
    private array $missing = [];

    public function handle(EventGalleryService $gallery): int
    {
        $owner = $this->option('user')
            ? User::query()->where('email', $this->option('user'))->first()
            : User::query()->where('is_admin', true)->orderBy('id')->first();

        if (! $owner) {
            $this->error('Nenhum dono encontrado para as demos. Informe --user=e-mail.');

            return self::FAILURE;
        }

        $imagesRoot = $this->option('images') ?: resource_path('demo');

        foreach (DemoCatalog::all() as $slug => $demo) {
            $taken = Event::withTrashed()->where('slug', $slug)->where('is_demo', false)->exists();

            if ($taken) {
                $this->warn("{$slug}: o endereço já é de um evento de verdade, pulei.");

                continue;
            }

            $event = DB::transaction(fn () => $this->sync($slug, $demo, $owner, "{$imagesRoot}/{$slug}"));

            $gallery->prepare($event);

            $this->info("{$slug}: pronta em ".route('events.show', ['event' => $slug]));
        }

        if ($this->missing !== []) {
            $this->newLine();
            $this->warn('Imagens que faltam (a demo funciona sem elas, mas fica mais bonita com):');

            foreach ($this->missing as $path) {
                $this->line("  - {$path}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $demo
     */
    private function sync(string $slug, array $demo, User $owner, string $images): Event
    {
        $event = Event::withTrashed()->where('slug', $slug)->first() ?? new Event;

        $disk = Storage::disk('public');
        $disk->deleteDirectory("demos/{$slug}");

        $event->forceFill([
            'user_id' => $owner->id,
            'slug' => $slug,
            'type' => $demo['type'],
            'title' => $demo['title'],
            // Never stored: it moves along with today (Event::showDemoMoment()).
            'event_date' => null,
            'event_time' => $demo['event_time'],
            'description' => $demo['description'],
            'story_title' => null,
            'story' => $demo['story'],
            'share_message' => $demo['share_message'],
            'cover_image' => $this->copyImage($images, 'capa', "demos/{$slug}"),
            'share_image' => null,
            'is_published' => true,
            'is_demo' => true,
            'archived_at' => null,
            'deleted_at' => null,
        ])->save();

        $event->appearance()->updateOrCreate([], $demo['appearance']);
        $event->rsvpSettings()->updateOrCreate([], $demo['rsvp']);
        $event->giftSettings()->updateOrCreate([], $demo['gifts']);
        $event->paymentSettings()->delete();

        // Every Premium feature, for good.
        $event->featureGrants()->delete();
        foreach (Feature::for(Event::class) as $feature) {
            $event->featureGrants()->create(['feature' => $feature, 'source' => FeatureGrant::SOURCE_ADMIN]);
        }
        $event->unsetRelation('featureGrants');

        // Nothing a visitor does is saved, but the panel could have added some.
        $event->orders()->each(fn ($order) => $order->items()->delete());
        $event->orders()->delete();
        $event->guests()->delete();
        $event->messages()->delete();
        $event->products()->withTrashed()->forceDelete();
        $event->locations()->delete();
        $event->sections()->delete();
        $event->albums()->delete();

        foreach ($demo['sections'] as $position => $section) {
            $event->sections()->create(['type' => $section, 'is_active' => true, 'position' => $position]);
        }

        foreach ($demo['locations'] as $position => $location) {
            $event->locations()->create([...$location, 'position' => $position]);
        }

        foreach ($demo['products'] as $product) {
            $event->products()->create([
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['price'],
                'quantity_total' => $product['quantity'],
                'quantity_purchased' => 0,
                'is_active' => true,
                'image' => $this->copyImage("{$images}/presentes", $product['image'], "demos/{$slug}/presentes"),
            ]);
        }

        // Newest first on the wall: the first message reads as the latest.
        foreach ($demo['messages'] as $index => $message) {
            $event->messages()->create([
                'author_name' => $message['author'],
                'is_anonymous' => $message['author'] === null,
                'message' => $message['message'],
                'approved_at' => now()->subHours($index * 5 + 1),
            ])->forceFill(['hosts_notified_at' => now()])->save();
        }

        foreach ($demo['albums'] as $position => $name) {
            $photos = $this->copyAlbum("{$images}/albuns/{$name}", "demos/{$slug}/albuns/".Str::slug($name));

            $event->albums()->create(['name' => $name, 'position' => $position, 'photos' => $photos]);
        }

        return $event->refresh();
    }

    /**
     * Copies "{dir}/{name}.jpg" (or .png, .webp) to the public disk, and
     * returns its path there — or null, noting it, when there's none.
     */
    private function copyImage(string $dir, string $name, string $target): ?string
    {
        foreach (self::IMAGE_EXTENSIONS as $extension) {
            $source = "{$dir}/{$name}.{$extension}";

            if (File::exists($source)) {
                $path = "{$target}/{$name}.{$extension}";
                Storage::disk('public')->put($path, File::get($source));

                return $path;
            }
        }

        $this->missing[] = "{$dir}/{$name}.jpg";

        return null;
    }

    /**
     * Every picture in the folder, in the order of their names.
     *
     * @return array<int, string>
     */
    private function copyAlbum(string $dir, string $target): array
    {
        if (! File::isDirectory($dir)) {
            $this->missing[] = "{$dir}/ (fotos do álbum)";

            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true))
            ->sortBy(fn ($file) => $file->getFilename(), SORT_NATURAL)
            ->map(function ($file) use ($target): string {
                $path = "{$target}/".Str::lower($file->getFilename());
                Storage::disk('public')->put($path, $file->getContents());

                return $path;
            })
            ->values()
            ->all();
    }
}
