import { LockKeyhole, Truck } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError';
import type { ReferenceOption } from '@/types/operations';

export default function AssignedVehicle({
    vehicle,
    error,
    blocked,
}: {
    vehicle: ReferenceOption | null;
    error?: string;
    blocked?: string | null;
}) {
    const message = error || blocked || undefined;
    return (
        <div className="min-w-0">
            <Label htmlFor="vehicle_id" className="flex items-center gap-2">
                <Truck className="size-4 text-primary" aria-hidden="true" />
                Mobil yang ditugaskan
                <LockKeyhole className="size-3 text-muted-foreground" aria-hidden="true" />
            </Label>
            <Input
                id="vehicle_id"
                className="mt-2 bg-secondary/50 font-medium"
                value={vehicle?.label ?? 'Belum ditetapkan'}
                readOnly
                aria-invalid={!!message}
                aria-describedby="assigned-vehicle-note"
            />
            <div id="assigned-vehicle-note" className="mt-2">
                {message ? (
                    <div role="alert">
                        <InputError message={message} />
                        {error && !blocked && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="mt-3"
                                onClick={() => window.location.reload()}
                            >
                                Muat ulang halaman
                            </Button>
                        )}
                    </div>
                ) : (
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        Mobil ditetapkan oleh admin dan otomatis digunakan untuk transaksi ini.
                    </p>
                )}
            </div>
        </div>
    );
}
