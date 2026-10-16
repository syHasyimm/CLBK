import { Badge } from '@/components/ui/badge';
import type { PayrollHistoryWorker } from '@/types/payroll-history';

export default function WorkerIdentity({ worker }: { worker: PayrollHistoryWorker }) {
    return (
        <div className="min-w-0">
            <h3 className="break-words text-sm font-semibold">{worker.name}</h3>
            <p className="mt-1 text-[11px] text-muted-foreground">{worker.code}</p>
            {!worker.is_active && (
                <Badge variant="outline" className="mt-2 rounded-md text-[10px]">
                    Karyawan nonaktif
                </Badge>
            )}
        </div>
    );
}
