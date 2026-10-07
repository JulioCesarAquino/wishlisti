import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    GuestContact,
    RsvpField,
    RsvpFieldMode,
} from '@/pages/events/types';

/**
 * Name plus the fields the host asks for, shared by the RSVP form and the
 * gift cart so a guest is identified the same way everywhere on the page.
 */
export type ContactForm = {
    name: string;
    whatsapp: string;
    email: string;
    cpf: string;
    age: string;
};

const FIELD_LABELS: Record<RsvpField, string> = {
    whatsapp: 'WhatsApp',
    email: 'E-mail',
    cpf: 'CPF',
    age: 'Idade',
};

const FIELD_ORDER: RsvpField[] = ['whatsapp', 'email', 'cpf', 'age'];

/**
 * "(61) 99999-9999" as the guest types — a landline "(61) 3333-4444" too.
 * The server compares only the digits, so any format matches.
 */
export function formatPhone(value: string): string {
    let digits = value.replace(/\D/g, '');

    // A number saved with the country code shows without it.
    if (digits.length > 11 && digits.startsWith('55')) {
        digits = digits.slice(2);
    }

    digits = digits.slice(0, 11);

    if (digits.length <= 2) {
        return digits.length ? `(${digits}` : '';
    }

    const area = `(${digits.slice(0, 2)}) `;
    const number = digits.slice(2);
    const split = number.length > 8 ? 5 : 4;

    return number.length > split
        ? `${area}${number.slice(0, split)}-${number.slice(split)}`
        : `${area}${number}`;
}

export function toContactForm(
    contact: GuestContact | null | undefined,
): ContactForm {
    return {
        name: contact?.name ?? '',
        whatsapp: contact?.whatsapp ?? '',
        email: contact?.email ?? '',
        cpf: contact?.cpf ?? '',
        age:
            contact?.age === null || contact?.age === undefined
                ? ''
                : String(contact.age),
    };
}

type ContactFieldsProps = {
    idPrefix: string;
    errorPrefix: string;
    value: ContactForm;
    fields: RsvpField[];
    requiredFields: RsvpField[];
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
            type:
                field === 'email'
                    ? 'email'
                    : field === 'age'
                      ? 'number'
                      : field === 'whatsapp'
                        ? 'tel'
                        : 'text',
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
                            inputMode={
                                key === 'cpf' || key === 'age'
                                    ? 'numeric'
                                    : key === 'whatsapp'
                                      ? 'tel'
                                      : undefined
                            }
                            min={key === 'age' ? 0 : undefined}
                            max={key === 'age' ? 120 : undefined}
                            placeholder={
                                key === 'age'
                                    ? 'Em anos'
                                    : key === 'whatsapp'
                                      ? '(61) 99999-9999'
                                      : undefined
                            }
                            aria-invalid={error ? true : undefined}
                            aria-describedby={
                                error ? `${idPrefix}_${key}_error` : undefined
                            }
                            value={
                                key === 'whatsapp'
                                    ? formatPhone(value[key])
                                    : value[key]
                            }
                            onChange={(e) =>
                                onChange({
                                    ...value,
                                    [key]:
                                        key === 'whatsapp'
                                            ? formatPhone(e.target.value)
                                            : e.target.value,
                                })
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
 * The fields the host asks for, in order — the age only on the RSVP (the
 * gift cart only needs to know who's giving).
 */
export function visibleFields(
    modes: Record<RsvpField, RsvpFieldMode>,
    withAge = true,
): RsvpField[] {
    return FIELD_ORDER.filter(
        (field) => modes[field] !== 'hidden' && (withAge || field !== 'age'),
    );
}

export function requiredFieldsOf(
    modes: Record<RsvpField, RsvpFieldMode>,
): RsvpField[] {
    return FIELD_ORDER.filter((field) => modes[field] === 'required');
}
