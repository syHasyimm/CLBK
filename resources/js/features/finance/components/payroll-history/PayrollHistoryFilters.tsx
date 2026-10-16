import InputError from '@/components/InputError';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { LoaderCircle, Search, SlidersHorizontal, X } from 'lucide-react';

import type { PayrollHistoryModel } from '@/features/finance/hooks/use-payroll-history';

export default function PayrollHistoryFilters({ model }: { model: PayrollHistoryModel }) {
    const {
        view,
        worker,
        errors,
        data,
        setData,
        busy,
        settled,
        datePrefix,
        filtered,
        visit,
        submit,
    } = model;

    return (
        <>
            <form
                onSubmit={submit}
                aria-label="Filter riwayat penggajian"
                aria-busy={busy}
                className="border-y bg-muted/20 p-5 sm:p-6"
            >
                {worker && (
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-secondary/60 px-3 py-2.5">
                        <p className="min-w-0 break-words text-xs">
                            Karyawan: <span className="font-semibold">{worker.name}</span>
                            {' · '}
                            {worker.code}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={busy}
                            onClick={() => visit(data, view, '')}
                        >
                            <X className="size-3.5" aria-hidden="true" />
                            Semua karyawan
                        </Button>
                    </div>
                )}
                <div className="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
                    <div className="min-w-0">
                        <Label htmlFor="payroll-history-search">Nama, kode, atau nomor</Label>
                        <div className="relative mt-2">
                            <Search
                                className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="payroll-history-search"
                                name="q"
                                className="h-10 pl-10"
                                placeholder={
                                    settled
                                        ? 'Cari karyawan atau timbangan...'
                                        : 'Cari karyawan atau voucher...'
                                }
                                maxLength={100}
                                value={data.q}
                                onChange={(event) => setData({ ...data, q: event.target.value })}
                                aria-invalid={!!errors.q}
                                aria-describedby={errors.q ? 'payroll-history-q-error' : undefined}
                            />
                        </div>
                        <InputError
                            id="payroll-history-q-error"
                            message={errors.q}
                            className="mt-2"
                        />
                    </div>
                    {(['from', 'to'] as const).map((key) => (
                        <div key={key} className="min-w-0">
                            <Label htmlFor={`payroll-history-${key}`}>
                                {datePrefix} {key === 'from' ? 'mulai' : 'sampai'}
                            </Label>
                            <Input
                                id={`payroll-history-${key}`}
                                name={key}
                                type="date"
                                className="mt-2 h-10"
                                value={data[key]}
                                onChange={(event) =>
                                    setData({ ...data, [key]: event.target.value })
                                }
                                aria-invalid={!!errors[key]}
                                aria-describedby={
                                    errors[key] ? `payroll-history-${key}-error` : undefined
                                }
                            />
                            <InputError
                                id={`payroll-history-${key}-error`}
                                message={errors[key]}
                                className="mt-2"
                            />
                        </div>
                    ))}
                </div>
                <div className="mt-4 flex flex-wrap items-end gap-3">
                    {!settled && (
                        <div className="w-full sm:w-48">
                            <Label htmlFor="payroll-history-status">Status voucher</Label>
                            <NativeSelect
                                id="payroll-history-status"
                                name="status"
                                className="mt-2 h-10"
                                value={data.status}
                                onChange={(event) =>
                                    setData({ ...data, status: event.target.value })
                                }
                                aria-invalid={!!errors.status}
                                aria-describedby={
                                    errors.status ? 'payroll-history-status-error' : undefined
                                }
                            >
                                <option value="">Semua status</option>
                                <option value="posted">Sah</option>
                                <option value="reversed">Dibalik</option>
                            </NativeSelect>
                            <InputError
                                id="payroll-history-status-error"
                                message={errors.status}
                                className="mt-2"
                            />
                        </div>
                    )}
                    <Button type="submit" variant="secondary" className="h-10" disabled={busy}>
                        {busy ? (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        ) : (
                            <SlidersHorizontal className="size-4" aria-hidden="true" />
                        )}
                        Tampilkan
                    </Button>
                    {filtered && (
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-10"
                            disabled={busy}
                            onClick={() => {
                                const next = { q: '', from: '', to: '', status: '' };
                                setData(next);
                                visit(next);
                            }}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Reset filter
                        </Button>
                    )}
                </div>
            </form>
        </>
    );
}
