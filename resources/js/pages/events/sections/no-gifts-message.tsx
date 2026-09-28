import { Heart } from 'lucide-react';
import { FreeAmountCard } from '@/pages/events/sections/free-amount-card';
import {
    bodyTextStyle,
    headingStyle,
    type EventData,
} from '@/pages/events/types';

type Props = {
    event: EventData;
    onContribute: () => void;
};

/**
 * The "no gifts" mode: just the host's message — plus, if they accept it,
 * the option to contribute any amount.
 */
export function NoGiftsMessage({ event, onContribute }: Props) {
    return (
        <div className="mx-auto max-w-2xl px-6 pb-12 text-center">
            <Heart
                className="mx-auto mb-3 size-8"
                style={{ color: event.secondary_color ?? undefined }}
            />
            <p
                className="mb-6 text-xl whitespace-pre-line"
                style={headingStyle(event)}
            >
                {event.gift_message}
            </p>
            {event.accepts_free_amount && (
                <div style={bodyTextStyle(event)}>
                    <FreeAmountCard event={event} onContribute={onContribute} />
                </div>
            )}
        </div>
    );
}
