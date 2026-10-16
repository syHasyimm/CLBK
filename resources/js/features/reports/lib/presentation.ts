import { rupiah } from '@/lib/currency';
import { weight } from '@/lib/weight';
import { statusLabels } from '@/types/operations';
import type { ReportColumn } from '@/types/reports';

export const reportGroups = [
    { label: 'Ringkasan usaha', views: ['summary'] },
    { label: 'Operasional', views: ['weighings', 'factory', 'sort'] },
    { label: 'Tagihan & upah', views: ['receivables', 'debts', 'payroll'] },
    { label: 'Keuangan', views: ['cash', 'receipts', 'expenses'] },
    { label: 'Administrasi', views: ['audit'] },
];

export const reportFilterLabels: Record<string, string> = {
    vehicle_id: 'Mobil',
    customer_id: 'Konsumen',
    factory_id: 'Pabrik',
    worker_id: 'Anggota muat',
    cash_account_id: 'Kas Usaha',
    expense_category_id: 'Kategori pengeluaran',
};

export function reportDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value + 'T00:00:00+07:00'));
}

export function formatReportValue(
    value: unknown,
    column: Pick<ReportColumn, 'key' | 'type'>,
): string {
    if (value == null) return '—';
    if (column.key === 'event') return value === 'reversal' ? 'Pembalikan' : 'Transaksi awal';
    if (column.key === 'status') return statusLabels[String(value)] ?? String(value);
    if (column.key === 'sale_type')
        return value === 'factory' ? 'Pabrik' : value === 'sort' ? 'Sortir' : String(value);
    if (column.key === 'method') return value === 'cash' ? 'Tunai' : 'Transfer bank';
    if (column.type === 'money') return rupiah(Number(value));
    if (column.type === 'weight') return weight(Number(value));
    return String(value);
}
