import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { GuestContact, RsvpContactField } from '@/pages/events/types';

/**
 * Name plus contact fields, shared by the RSVP form and the gift cart so a
 * guest is identified the same way (with the fields the host made
 * mandatory) everywhere on the page.
 */
export type ContactForm = {
    name: string;
    whatsapp: string;
    email: string;
    cpf: string;
};

const FIELD_LABELS: Record<RsvpContactField, string> = {
    whatsapp: 'WhatsApp',
    email: 'E-mail',
    cpf: 'CPF',
};

export function toContactForm(
    contact: GuestContact | null | undefined,
): ContactForm {
    return {
        name: contact?.name ?? '',
        whatsapp: contact?.whatsapp ?? '',
        email: contact?.email ?? '',
        cpf: contact?.cpf ?? '',
    };
}

type ContactFieldsProps = {
    idPrefix: string;
    errorPrefix: string;
    value: ContactForm;
    fields: RsvpContactField[];
    requiredFields: RsvpContactField[];
    nameLabel: string;
    errors: Record<string, string | undefined>;
    onChange: (value: ContactForm) => void;
};

export function ContactFields({
    idPrefix,
    errorPrefix,
    value,
    fields,
    requiredFields,
    nameLabel,
    errors,
    onChange,
}: ContactFieldsProps) {
    const inputs: { key: keyof ContactForm; label: string; type: string }[] = [
        { key: 'name', label: nameLabel, type: 'text' },
        ...fields.map((field) => ({
            key: field,
            label: requiredFields.includes(field)
                ? FIELD_LABELS[field]
                : `${FIELD_LABELS[field]} (opcional)`,
            type: field === 'email' ? 'email' : 'text',
        })),
    ];

    return (
        <>
            {inputs.map(({ key, label, type }) => {
                const error = errors[`${errorPrefix}.${key}`];

                return (
                    <div key={key} className="grid gap-1.5">
                        <Label htmlFor={`${idPrefix}_${key}`}>{label}</Label>
                        <Input
                            id={`${idPrefix}_${key}`}
                            type={type}
                            inputMode={key === 'cpf' ? 'numeric' : undefined}
                            aria-invalid={error ? true : undefined}
                            aria-describedby={
                                error ? `${idPrefix}_${key}_error` : undefined
                            }
                            value={value[key]}
                            onChange={(e) =>
                                onChange({ ...value, [key]: e.target.value })
                            }
                        />
                        {error && (
                            <p
                                id={`${idPrefix}_${key}_error`}
                                className="text-sm text-red-600"
                            >
                                {error}
                            </p>
                        )}
                    </div>
                );
            })}
        </>
    );
}

/**
 * The guest's own fields: WhatsApp and e-mail are always offered, the CPF
 * only when the host made it mandatory.
 */
export function guestContactFields(
    requiredFields: RsvpContactField[],
): RsvpContactField[] {
    return [
        'whatsapp',
        'email',
        ...(requiredFields.includes('cpf') ? (['cpf'] as const) : []),
    ];
}
