import { Download, LoaderCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePwa } from './PwaProvider';

export default function PwaInstallButton() {
    const { canInstall, installing, install } = usePwa();
    if (!canInstall) return null;
    return (
        <Button
            type="button"
            variant="outline"
            className="w-full gap-2 print:hidden"
            disabled={installing}
            onClick={() => void install()}
        >
            {installing ? (
                <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
            ) : (
                <Download className="size-4" aria-hidden="true" />
            )}
            Pasang aplikasi
        </Button>
    );
}
