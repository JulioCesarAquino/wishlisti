import { HandHeart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    accentButtonStyle,
    bodyTextStyle,
    headingStyle,
    type EventData,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    onContribute: () => void;
};

/**
 * A gift from the heart: any amount, without picking an item (premium,
 * paid online).
 */
export function FreeAmountCard({ event, onContribute }: Props) {
    return (
        <div
            className="mx-auto mb-6 max-w-xl rounded-lg border border-black/10 bg-white/50 p-5 text-center"
            style={bodyTextStyle(event)}
        >
            <HandHeart
                className="mx-auto mb-2 size-8"
                style={{ color: event.secondary_color ?? undefined }}
            />
            <p className="mb-1 font-medium" style={headingStyle(event)}>
                Presentear com qualquer valor
            </p>
            <p className="mb-4 text-sm">
                Sem precisar escolher um item: contribua com o valor que o seu
                coração mandar.
            </p>
            <Button style={accentButtonStyle(event)} onClick={onContribute}>
                Contribuir
            </Button>
        </div>
    );
}
