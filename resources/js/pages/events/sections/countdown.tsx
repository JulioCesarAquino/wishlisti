import { useEffect, useState } from 'react';
import type { EventData } from '@/pages/events/types';
import {
    accentButtonStyle,
    bodyTextStyle,
    DEFAULT_PRIMARY_COLOR,
} from '@/pages/events/types';

type Elapsed = {
    /** Counting down to the event, or — once it started — up since it. */
    passed: boolean;
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
};

function calculate(target: Date): Elapsed {
    const diff = target.getTime() - Date.now();
    const abs = Math.abs(diff);

    return {
        passed: diff <= 0,
        days: Math.floor(abs / (1000 * 60 * 60 * 24)),
        hours: Math.floor((abs / (1000 * 60 * 60)) % 24),
        minutes: Math.floor((abs / (1000 * 60)) % 60),
        seconds: Math.floor((abs / 1000) % 60),
    };
}

/**
 * Time left until the event starts; once it has, the time since it ("Já se
 * passaram"), in flip-clock cards — the page lives on after the big day.
 */
export function Countdown({ event }: { event: EventData }) {
    const [elapsed, setElapsed] = useState<Elapsed | null>(null);

    useEffect(() => {
        if (!event.starts_at) {
            return;
        }

        const target = new Date(event.starts_at);
        setElapsed(calculate(target));

        const interval = setInterval(() => {
            setElapsed(calculate(target));
        }, 1000);

        return () => clearInterval(interval);
    }, [event.starts_at]);

    if (!elapsed) {
        return null;
    }

    const units: { label: string; value: number }[] = [
        { label: 'Dias', value: elapsed.days },
        { label: 'Horas', value: elapsed.hours },
        { label: 'Minutos', value: elapsed.minutes },
        { label: 'Segundos', value: elapsed.seconds },
    ];

    return (
        <div className="py-6">
            {elapsed.passed && (
                <p
                    className="mb-4 text-xl tracking-wide"
                    style={bodyTextStyle(event)}
                >
                    Já se passaram
                </p>
            )}
            <div className="flex justify-center gap-3">
                {units.map((unit) => (
                    <div key={unit.label} className="text-center">
                        <div
                            className="relative mb-1 min-w-16 overflow-hidden rounded-md px-3 py-2 text-2xl font-semibold tabular-nums"
                            style={accentButtonStyle(event)}
                        >
                            {String(unit.value).padStart(2, '0')}
                            {elapsed.passed && (
                                // The seam of a flip-clock card, in the
                                // page's color.
                                <span
                                    aria-hidden
                                    className="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2"
                                    style={{
                                        backgroundColor:
                                            event.primary_color ??
                                            DEFAULT_PRIMARY_COLOR,
                                    }}
                                />
                            )}
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
        </div>
    );
}
