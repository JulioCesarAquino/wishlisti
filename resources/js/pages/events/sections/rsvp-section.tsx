import { useForm } from '@inertiajs/react';
import { CalendarCheck, CalendarX } from 'lucide-react';
import { useState } from 'react';
import { store as storeRsvp } from '@/routes/rsvp';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ContactFields,
    guestContactFields,
    toContactForm,
    type ContactForm,
} from '@/pages/events/sections/contact-fields';
import {
    accentButtonStyle,
    bodyTextStyle,
    headingStyle,
    type EventData,
    type Guest,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    guest: Guest | null;
};

function resizeCompanions(
    companions: ContactForm[],
    guestsCount: number,
): ContactForm[] {
    const size = Math.max(0, Math.min(guestsCount, 20) - 1);

    return Array.from(
        { length: size },
        (_, index) => companions[index] ?? toContactForm(null),
    );
}

export function RsvpSection({ event, guest }: Props) {
    const [justResponded, setJustResponded] = useState(false);
    const [editing, setEditing] = useState(false);

    const requiredFields = event.rsvp_required_fields;
    const collectsCompanions = event.rsvp_collect_companions;

    const guestFields = guestContactFields(requiredFields);

    const initialCount = guest?.rsvp_guests_count ?? 1;

    const form = useForm({
        guest: toContactForm(guest),
        attending: null as boolean | null,
        guests_count: initialCount,
        companions: collectsCompanions
            ? resizeCompanions(
                  (guest?.companions ?? []).map(toContactForm),
                  initialCount,
              )
            : [],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const setGuestsCount = (count: number) => {
        form.setData((data) => ({
            ...data,
            guests_count: count,
            companions: collectsCompanions
                ? resizeCompanions(data.companions, count)
                : [],
        }));
    };

    const submit = () => {
        form.post(storeRsvp({ event: event.slug }).url, {
            preserveScroll: true,
            onSuccess: () => {
                setJustResponded(true);
                setEditing(false);
            },
        });
    };

    const alreadyResponded = guest?.rsvp_status && !editing;

    if (alreadyResponded || justResponded) {
        // After a successful submit the page reloads with the saved guest,
        // whose headcount may differ from what was typed (a companion who
        // had already confirmed on their own isn't counted twice).
        const attending = guest?.rsvp_status
            ? guest.rsvp_status === 'confirmed'
            : form.data.attending;
        const count = guest?.rsvp_status
            ? guest.rsvp_guests_count
            : form.data.guests_count;

        return (
            <div className="mx-auto max-w-lg px-6 py-16 text-center">
                {attending ? (
                    <CalendarCheck
                        className="mx-auto mb-3 size-10"
                        style={{ color: event.secondary_color ?? undefined }}
                    />
                ) : (
                    <CalendarX
                        className="mx-auto mb-3 size-10 opacity-50"
                        style={bodyTextStyle(event)}
                    />
                )}
                <h2
                    className="mb-2 text-2xl font-semibold"
                    style={headingStyle(event)}
                >
                    {attending
                        ? `Presença confirmada para ${count} pessoa${count === 1 ? '' : 's'}!`
                        : 'Você avisou que não vai poder comparecer.'}
                </h2>
                <p className="mb-6" style={bodyTextStyle(event)}>
                    Obrigado por responder.
                </p>
                <Button variant="outline" onClick={() => setEditing(true)}>
                    Alterar resposta
                </Button>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-lg px-6 py-8">
            <h2
                className="mb-1 text-center text-2xl font-semibold"
                style={headingStyle(event)}
            >
                Confirmar presença
            </h2>
            <p className="mb-6 text-center" style={bodyTextStyle(event)}>
                Avise se você vai poder comparecer ao evento.
            </p>

            <div className="space-y-4">
                <ContactFields
                    idPrefix="rsvp"
                    errorPrefix="guest"
                    value={form.data.guest}
                    fields={guestFields}
                    requiredFields={requiredFields}
                    nameLabel="Seu nome"
                    errors={errors}
                    onChange={(value) => form.setData('guest', value)}
                />

                <div className="grid gap-1.5">
                    <Label>Você vai comparecer?</Label>
                    <div className="flex gap-3">
                        <Button
                            type="button"
                            variant={
                                form.data.attending === true
                                    ? 'default'
                                    : 'outline'
                            }
                            className="flex-1"
                            style={
                                form.data.attending === true
                                    ? accentButtonStyle(event)
                                    : undefined
                            }
                            onClick={() => form.setData('attending', true)}
                        >
                            Vou comparecer
                        </Button>
                        <Button
                            type="button"
                            variant={
                                form.data.attending === false
                                    ? 'default'
                                    : 'outline'
                            }
                            className="flex-1"
                            onClick={() => form.setData('attending', false)}
                        >
                            Não vou poder
                        </Button>
                    </div>
                    {form.errors.attending && (
                        <p className="text-sm text-red-600">
                            {form.errors.attending}
                        </p>
                    )}
                </div>

                {form.data.attending === true && (
                    <div className="grid gap-1.5">
                        <Label htmlFor="rsvp_count">
                            Quantas pessoas (incluindo você)?
                        </Label>
                        <Input
                            id="rsvp_count"
                            type="number"
                            min={1}
                            max={20}
                            value={form.data.guests_count}
                            onChange={(e) =>
                                setGuestsCount(Number(e.target.value))
                            }
                        />
                        {form.errors.guests_count && (
                            <p className="text-sm text-red-600">
                                {form.errors.guests_count}
                            </p>
                        )}
                        {errors.companions && (
                            <p className="text-sm text-red-600">
                                {errors.companions}
                            </p>
                        )}
                    </div>
                )}

                {form.data.attending === true &&
                    form.data.companions.map((companion, index) => (
                        <fieldset
                            key={index}
                            className="space-y-3 rounded-lg border p-4"
                        >
                            <legend
                                className="px-1 text-sm font-medium"
                                style={bodyTextStyle(event)}
                            >
                                Acompanhante {index + 1}
                            </legend>
                            <ContactFields
                                idPrefix={`rsvp_companion_${index}`}
                                errorPrefix={`companions.${index}`}
                                value={companion}
                                fields={requiredFields}
                                requiredFields={requiredFields}
                                nameLabel="Nome"
                                errors={errors}
                                onChange={(value) =>
                                    form.setData(
                                        'companions',
                                        form.data.companions.map(
                                            (current, i) =>
                                                i === index ? value : current,
                                        ),
                                    )
                                }
                            />
                        </fieldset>
                    ))}

                <Button
                    className="w-full"
                    style={accentButtonStyle(event)}
                    disabled={form.processing || form.data.attending === null}
                    onClick={submit}
                >
                    Enviar confirmação
                </Button>
            </div>
        </div>
    );
}
