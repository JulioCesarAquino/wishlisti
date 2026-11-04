<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Events\Event;
use App\Models\Events\EventAlbum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A gallery photo as it was sent, to keep: "attachment", so phones save it
 * instead of opening it in a new tab (the download attribute of a link is
 * ignored by iOS for files on another origin, and by some Android browsers).
 */
class EventPhotoDownloadController extends Controller
{
    public function __invoke(Request $request, Event $event, EventAlbum $album, string $photo): StreamedResponse
    {
        abort_unless($event->isViewableBy($request->user()), 404);

        $path = $album->findPhoto($photo);
        $disk = Storage::disk('public');

        abort_if($path === null || ! $disk->exists($path), 404);

        $position = array_search($path, $album->photoPaths(), true) + 1;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
        $name = Str::slug("{$event->title} {$album->name} {$position}").".{$extension}";

        return $disk->download($path, $name);
    }
}
