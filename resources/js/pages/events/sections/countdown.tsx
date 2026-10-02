import { useEffect, useState } from 'react';
import type { EventData } from '@/pages/events/types';
import { accentButtonStyle } from '@/pages/events/types';

type TimeLeft = {
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
};

function calculateTimeLeft(target: Date): TimeLeft | null {
    const diff = target.getTime() - Date.now();

    if (diff <= 0) {
        return null;
    }

    return {
        days: Math.floor(diff / (1000 * 60 * 60 * 24)),
        hours: Math.floor((diff / (1000 * 60 * 60)) % 24),
        minutes: Math.floor((diff / (1000 * 60)) % 60),
        seconds: Math.floor((diff / 1000) % 60),
    };
}

export function Countdown({ event }: { event: EventData }) {
    const [timeLeft, setTimeLeft] = useState<TimeLeft | null>(null);

    useEffect(() => {
        if (!event.event_date) {
            return;
        }

        const target = new Date(`${event.event_date}T00:00:00`);
        setTimeLeft(calculateTimeLeft(target));

        const interval = setInterval(() => {
            setTimeLeft(calculateTimeLeft(target));
        }, 1000);

        return () => clearInterval(interval);
    }, [event.event_date]);

    if (!timeLeft) {
        return null;
    }

    const units: { label: string; value: number }[] = [
        { label: 'Dias', value: timeLeft.days },
        { label: 'Horas', value: timeLeft.hours },
        { label: 'Minutos', value: timeLeft.minutes },
        { label: 'Segundos', value: timeLeft.seconds },
    ];

    return (
        <div className="flex justify-center gap-3 py-6">
            {units.map((unit) => (
                <div key={unit.label} className="text-center">
                    <div
                        className="mb-1 min-w-16 rounded-md px-3 py-2 text-2xl font-semibold tabular-nums"
                        style={accentButtonStyle(event)}
                    >
                        {String(unit.value).padStart(2, '0')}
                    </div>
                    <span
                        className="text-xs"
                        style={{
                            color: event.font_color_secondary ?? undefined,
                        }}
                    >
                        {unit.label}
                    </span>
                </div>
            ))}
        </div>
    );
}
