import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Timeline } from '@/types/timeline';
import { phaseColors, phaseLabels } from '../constants/constants';

interface TimelineLegendProps {
    timelines: Timeline[];
}

export function TimelineLegend({ timelines }: TimelineLegendProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">SDLC Phase Legend</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {Object.entries(phaseLabels).map(([phase, label]) => (
                        <div
                            key={phase}
                            className="flex items-center gap-3 rounded-lg border p-3"
                        >
                            <div
                                className={`h-8 w-8 rounded ${phaseColors[phase]}`}
                            />
                            <div>
                                <div className="text-sm font-medium">
                                    {label}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {
                                        timelines.filter(
                                            (t) => t.phase === phase,
                                        ).length
                                    }{' '}
                                    active
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
