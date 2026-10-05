import { useForm } from '@inertiajs/react';
import { MessageCircleHeart } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    accentButtonStyle,
    bodyTextStyle,
    headingStyle,
    type EventData,
    type GuestMessage,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    messages: GuestMessage[];
    defaultName: string;
};

/**
 * The guestbook (premium): approved messages, and a form to leave one —
 * it shows up only after the hosts approve it.
 */
export function GuestbookSection({ event, messages, defaultName }: Props) {
    const [sent, setSent] = useState(false);

    const form = useForm({
        author_name: defaultName,
        message: '',
        anonymous: false,
    });

    const submit = () => {
        form.post(`/${event.slug}/messages`, {
            preserveScroll: true,
            onSuccess: () => {
                setSent(true);
                form.reset('message');
            },
        });
    };

    return (
        <div className="mx-auto max-w-2xl px-6 py-8">
            <h2
                className="mb-1 text-center text-2xl font-semibold"
                style={headingStyle(event)}
            >
                Mural de recados
            </h2>
            <p className="mb-6 text-center" style={bodyTextStyle(event)}>
                Deixe uma mensagem de carinho, com ou sem presente.
            </p>

            <div className="mb-8 space-y-3 rounded-lg border border-black/10 bg-white/50 p-4">
                <label
                    className="flex cursor-pointer items-start gap-2 text-sm"
                    style={bodyTextStyle(event)}
                >
                    <input
                        type="checkbox"
                        className="mt-0.5 size-4 accent-current"
                        checked={form.data.anonymous}
                        onChange={(e) =>
                            form.setData('anonymous', e.target.checked)
                        }
                    />
                    <span>
                        Enviar anonimamente
                        <span className="block text-xs opacity-80">
                            O recado aparece no mural assinado como
                            &quot;Anônimo&quot;. O nome fica opcional e, se
                            preenchido, só a equipe do Wishlisti vê.
                        </span>
                    </span>
                </label>
                <div className="grid gap-1.5">
                    <Label htmlFor="guestbook_name">
                        {form.data.anonymous
                            ? 'Seu nome (opcional)'
                            : 'Seu nome'}
                    </Label>
                    <Input
                        id="guestbook_name"
                        aria-invalid={
                            form.errors.author_name ? true : undefined
                        }
                        value={form.data.author_name}
                        onChange={(e) =>
                            form.setData('author_name', e.target.value)
                        }
                    />
                    {form.errors.author_name && (
                        <p className="text-sm text-red-600">
                            {form.errors.author_name}
                        </p>
                    )}
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="guestbook_message">Recado</Label>
                    <Textarea
                        id="guestbook_message"
                        aria-invalid={form.errors.message ? true : undefined}
                        rows={3}
                        value={form.data.message}
                        onChange={(e) =>
                            form.setData('message', e.target.value)
                        }
                    />
                    {form.errors.message && (
                        <p className="text-sm text-red-600">
                            {form.errors.message}
                        </p>
                    )}
                </div>
                {sent && (
                    <p className="text-sm" style={bodyTextStyle(event)}>
                        Obrigado! Seu recado aparece aqui depois que os
                        anfitriões aprovarem.
                    </p>
                )}
                <Button
                    className="w-full"
                    style={accentButtonStyle(event)}
                    disabled={form.processing}
                    onClick={submit}
                >
                    Enviar recado
                </Button>
            </div>

            {messages.length === 0 ? (
                <p className="text-center text-sm" style={bodyTextStyle(event)}>
                    Seja o primeiro a deixar um recado.
                </p>
            ) : (
                <ul className="space-y-3">
                    {messages.map((message) => (
                        <li
                            key={message.id}
                            className="rounded-lg border border-black/10 bg-white/60 p-4"
                        >
                            <MessageCircleHeart
                                className="mb-2 size-5"
                                style={{
                                    color: event.secondary_color ?? undefined,
                                }}
                            />
                            <p
                                className="mb-2 whitespace-pre-line"
                                style={bodyTextStyle(event)}
                            >
                                {message.message}
                            </p>
                            <p
                                className="text-sm font-medium"
                                style={headingStyle(event)}
                            >
                                — {message.author_name}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
