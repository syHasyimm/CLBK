import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { PayeeCard } from '@/types/finance';
import { rupiah } from '@/lib/currency';
import PaymentStatusBadge from './PaymentStatusBadge';

export default function MobilePayeeRow({
    card,
    href,
    payroll = false,
}: {
    card: PayeeCard;
    href: string;
    payroll?: boolean;
}) {
    const initials = card.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toLocaleUpperCase('id-ID');
    return (
        <Link href={href} className="mobile-payee-row lg:hidden">
            <span className="mobile-payee-initials" aria-hidden="true">
                {initials}
            </span>
            <span className="mobile-payee-content">
                <span className="mobile-payee-name">{card.name}</span>
                <span className="mobile-payee-detail">
                    {card.ticket_count} {payroll ? 'pekerjaan' : 'tiket'} dalam filter
                    {!card.is_active ? ' · Nonaktif' : ''}
                </span>
                <strong className="mobile-payee-amount">{rupiah(card.remaining_idr)}</strong>
                <span className="mobile-payee-status">
                    <PaymentStatusBadge
                        status={
                            card.remaining_idr === 0
                                ? 'paid'
                                : card.paid_idr > 0
                                  ? 'partial'
                                  : 'unpaid'
                        }
                    />
                    {payroll && <span>Periksa & bayar</span>}
                </span>
            </span>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        </Link>
    );
}
