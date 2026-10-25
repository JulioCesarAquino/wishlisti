import { useForm } from '@inertiajs/react';
import { CalendarCheck, CalendarX, MailOpen } from 'lucide-react';
import { useState } from 'react';
import { store as storeRsvp } from '@/routes/rsvp';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ContactFields,
    requiredFieldsOf,
    toContactForm,
    visibleFields,
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
    maxPeople: number,
): ContactForm[] {
    const size = Math.max(0, Math.min(guestsCount, maxPeople) - 1);

    return Array.from(
        { length: size },
        (_, index) => companions[index] ?? toContactForm(null),
    );
}

export function RsvpSection({ event, guest }: Props) {
    const [justResponded, setJustResponded] = useState(false);
    const [editing, setEditing] = useState(false);
    // Answering for another person on the same phone: the event's limit,
    // not the previous guest's.
    const [answeringForSomeoneElse, setAnsweringForSomeoneElse] =
        useState(false);

    const fields = visibleFields(event.rsvp_fields);
    const requiredFields = requiredFieldsOf(event.rsvp_fields);
    const companionFields = visibleFields(event.rsvp_companion_fields);
    const companionRequiredFields = requiredFieldsOf(
        event.rsvp_companion_fields,
    );
    const collectsCompanions = event.rsvp_collect_companions;
    const childAgeLimit = event.rsvp_child_age_limit;

    const initialCount = guest?.rsvp_guests_count ?? 1;

    const maxCompanions =
        guest && !answeringForSomeoneElse
            ? guest.max_companions
            : event.rsvp_max_companions;
    const maxPeople = maxCompanions + 1;

    // The host only accepts confirmations by the personal invitation link,
    // and this browser didn't open one.
    const locked =
        event.rsvp_invite_only &&
        (!guest?.is_invited || answeringForSomeoneElse);

    // The counts are kept as typed (text): a number field holding a number
    // turns a cleared field into 0 — and then "3" typed after it into "03".
    const form = useForm({
        guest: toContactForm(guest),
        attending: null as boolean | null,
        guests_count: String(initialCount),
        children_count: String(guest?.rsvp_children_count ?? 0),
        companions: collectsCompanions
            ? resizeCompanions(
                  (guest?.companions ?? []).map(toContactForm),
                  initialCount,
                  maxPeople,
              )
            : [],
    });

    const errors = form.errors as Record<string, string | undefined>;

    // On a phone shared by a family, the page remembers whoever answered
    // last: someone else answering starts from a blank form, so nothing of
    // the previous person's answer (their phone, their companions) is
    // carried into theirs.
    const answerAsSomeoneElse = () => {
        form.clearErrors();
        form.setData({
            guest: toContactForm(null),
            attending: null,
            guests_count: '1',
            children_count: '0',
            companions: [],
        });
        setJustResponded(false);
        setEditing(true);
        setAnsweringForSomeoneElse(true);
    };

    const changeAnswer = () => {
        setJustResponded(false);
        setEditing(true);
    };

    const backToAnswer = () => {
        form.reset();
        form.clearErrors();
        setEditing(false);
        setAnsweringForSomeoneElse(false);
    };

    const firstName = guest?.name.trim().split(/\s+/)[0] ?? '';

    const guestsCount = Number(form.data.guests_count) || 0;

    const setGuestsCount = (typed: string) => {
        const count = Math.min(maxPeople, Number(typed) || 0);

        form.setData((data) => ({
            ...data,
            guests_count: typed,
            // Someone coming alone sends 0 children.
            children_count: count === 1 ? '0' : data.children_count,
            // A field cleared to type another number keeps the companions
            // filled in so far.
            companions:
                collectsCompanions && count >= 1
                    ? resizeCompanions(data.companions, count, maxPeople)
                    : data.companions,
        }));
    };

    const submit = () => {
        form.post(storeRsvp({ event: event.slug }).url, {
            preserveScroll: true,
            onSuccess: () => {
                setJustResponded(true);
                setEditing(false);
                // The page now holds whoever just answered.
                setAnsweringForSomeoneElse(false);
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
            : guestsCount;

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
                    {firstName && `${firstName}, `}
                    {attending
                        ? `${firstName ? 'presença' : 'Presença'} confirmada para ${count} pessoa${count === 1 ? '' : 's'}!`
                        : `${firstName ? 'você' : 'Você'} avisou que não vai poder comparecer.`}
                </h2>
                <p className="mb-6" style={bodyTextStyle(event)}>
                    Obrigado por responder.
                </p>
                {!locked && (
                    <Button variant="outline" onClick={changeAnswer}>
                        Alterar resposta
                    </Button>
                )}
                {firstName && !event.rsvp_invite_only && (
                    <p
                        className="mt-6 text-sm opacity-80"
                        style={bodyTextStyle(event)}
                    >
                        Outra pessoa vai confirmar neste celular?{' '}
                        <button
                            type="button"
                            className="font-medium underline underline-offset-4"
                            onClick={answerAsSomeoneElse}
                        >
                            Confirmar outra pessoa
                        </button>
                    </p>
                )}
            </div>
        );
    }

    if (locked) {
        return (
            <div className="mx-auto max-w-lg px-6 py-16 text-center">
                <MailOpen
                    className="mx-auto mb-3 size-10 opacity-50"
                    style={bodyTextStyle(event)}
                />
                <h2
                    className="mb-2 text-2xl font-semibold"
                    style={headingStyle(event)}
                >
                    Confirmar presença
                </h2>
                <p style={bodyTextStyle(event)}>
                    A confirmação de presença é feita pelo link que você recebeu
                    no seu convite. Abra esse link para confirmar. Não recebeu?
                    Fale com os anfitriões.
                </p>
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
                    fields={fields}
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

                {form.data.attending === true && maxCompanions === 0 && (
                    <p className="text-sm" style={bodyTextStyle(event)}>
                        Seu convite é individual.
                    </p>
                )}

                {form.data.attending === true &&
                    maxCompanions === 0 &&
                    form.errors.guests_count && (
                        <p className="text-sm text-red-600">
                            {form.errors.guests_count}
                        </p>
                    )}

                {form.data.attending === true && maxCompanions > 0 && (
                    <div className="grid gap-1.5">
                        <Label htmlFor="rsvp_count">
                            Quantas pessoas (incluindo você)?
                        </Label>
                        <Input
                            id="rsvp_count"
                            type="number"
                            min={1}
                            max={maxPeople}
                            inputMode="numeric"
                            value={form.data.guests_count}
                            onChange={(e) => setGuestsCount(e.target.value)}
                        />
                        {maxPeople < 20 && (
                            <p
                                className="text-sm opacity-80"
                                style={bodyTextStyle(event)}
                            >
                                {`Seu convite é para até ${maxPeople} pessoas, contando com você.`}
                            </p>
                        )}
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

                {/* Only for a party: someone coming alone sends 0. */}
                {form.data.attending === true &&
                    event.rsvp_asks_children_count &&
                    guestsCount > 1 && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="rsvp_children">
                                {`Quantas dessas pessoas têm menos de ${childAgeLimit} anos?`}
                            </Label>
                            <Input
                                id="rsvp_children"
                                type="number"
                                inputMode="numeric"
                                min={0}
                                max={guestsCount}
                                value={form.data.children_count}
                                onChange={(e) =>
                                    form.setData(
                                        'children_count',
                                        e.target.value,
                                    )
                                }
                            />
                            {errors.children_count && (
                                <p className="text-sm text-red-600">
                                    {errors.children_count}
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
                                fields={companionFields}
                                requiredFields={companionRequiredFields}
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

                {guest?.rsvp_status && (
                    <Button
                        variant="ghost"
                        className="w-full"
                        onClick={backToAnswer}
                    >
                        Voltar
                    </Button>
                )}
            </div>
        </div>
    );
}
