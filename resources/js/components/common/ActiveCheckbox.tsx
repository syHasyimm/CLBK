import { Label } from '@/components/ui/label';
export default function ActiveCheckbox({
    value,
    onChange,
    label = 'Data aktif',
}: {
    value: boolean;
    onChange: (value: boolean) => void;
    label?: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border bg-muted/40 p-4">
            <input
                id="is_active"
                name="is_active"
                type="checkbox"
                checked={value}
                onChange={(e) => onChange(e.target.checked)}
                className="size-4 rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
            />
            <div>
                <Label htmlFor="is_active">{label}</Label>
                <p className="mt-1 text-xs text-muted-foreground">
                    Data nonaktif tetap tersimpan dalam riwayat.
                </p>
            </div>
        </div>
    );
}
