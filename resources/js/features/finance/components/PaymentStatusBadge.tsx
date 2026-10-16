import { Badge } from '@/components/ui/badge';
import { paymentLabels } from '@/types/finance';
export default function PaymentStatusBadge({ status }: { status: string }) {
    return (
        <Badge variant={status === 'paid' ? 'secondary' : 'outline'}>
            {paymentLabels[status] ?? status}
        </Badge>
    );
}
