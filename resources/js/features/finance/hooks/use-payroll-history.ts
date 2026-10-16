import type { PayrollHistoryProps, PayrollHistoryView } from '@/types/payroll-history';
import useFilterVisit from '@/hooks/use-filter-visit';
import { usePage } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';

export default function usePayrollHistory({
    view = 'settled',
    filters,
    worker,
    rows,
    documents,
    summary,
}: PayrollHistoryProps) {
    const { errors } = usePage().props;
    const [data, setData] = useState({
        q: filters.q ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        status: filters.status ?? '',
    });
    const { busy, visit: navigate } = useFilterVisit(() => route('payroll.history'), {
        preserveState: true,
    });
    const settled = view === 'settled';
    const records = settled ? rows : documents;
    const datePrefix = settled ? 'Pekerjaan' : 'Pembayaran';
    const filtered = !!(data.q || data.from || data.to || data.status);

    useEffect(() => {
        setData({
            q: filters.q ?? '',
            from: filters.from ?? '',
            to: filters.to ?? '',
            status: filters.status ?? '',
        });
    }, [filters.q, filters.from, filters.to, filters.status, view]);

    function visit(
        values: typeof data,
        nextView: PayrollHistoryView = view,
        workerId = filters.worker_id,
    ) {
        navigate({
            view: nextView,
            q: values.q,
            from: values.from,
            to: values.to,
            ...(workerId ? { worker_id: workerId } : {}),
            ...(nextView === 'payments' ? { status: values.status } : {}),
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        visit(data);
    }
    return {
        view,
        filters,
        worker,
        rows,
        documents,
        summary,
        errors,
        data,
        setData,
        busy,
        settled,
        records,
        datePrefix,
        filtered,
        visit,
        submit,
    };
}

export type PayrollHistoryModel = ReturnType<typeof usePayrollHistory>;
