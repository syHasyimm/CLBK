import InputError from '@/components/InputError';
import { Label } from '@/components/ui/label';

export default function WhatsAppConsent({
    checked,
    onChange,
    error,
}: {
    checked: boolean;
    onChange: (value: boolean) => void;
    error?: string;
}) {
    return (
        <div className="rounded-lg border bg-muted/30 p-4">
            <div className="flex items-start gap-3">
                <input
                    id="whatsapp_enabled"
                    name="whatsapp_enabled"
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    className="mt-0.5 size-4 rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                    aria-describedby="whatsapp-consent-help"
                />
                <div>
                    <Label htmlFor="whatsapp_enabled">Kirim notifikasi WhatsApp</Label>
                    <p
                        id="whatsapp-consent-help"
                        className="mt-1.5 text-xs leading-relaxed text-muted-foreground"
                    >
                        Konsumen bersedia menerima hasil timbangan tanpa harga dan konfirmasi
                        pembayaran melalui nomor telepon di atas.
                    </p>
                </div>
            </div>
            <InputError message={error} className="mt-2" />
        </div>
    );
}
