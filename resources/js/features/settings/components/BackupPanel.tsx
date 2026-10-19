import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError';
import { usePwaReloadGuard } from '@/lib/pwa-reload-guard';

export interface BackupStatus {
    files: { id: string; bytes: number; created_at: string }[];
    error: string | null;
    retention: { daily: number; weekly: number };
    time: string;
}

export default function BackupPanel({ status }: { status: BackupStatus }) {
    const backup = useForm({ current_password: '' });
    usePwaReloadGuard(backup.isDirty || backup.processing);
    function create(event: FormEvent) {
        event.preventDefault();
        backup.post(route('backups.store'), {
            preserveScroll: true,
            onFinish: () => backup.reset('current_password'),
        });
    }
    return (
        <section
            className="mt-8 max-w-3xl rounded-xl border bg-card p-6 sm:p-8"
            aria-labelledby="backup-heading"
        >
            <h2 id="backup-heading" className="text-lg font-semibold">
                Cadangan & pemulihan
            </h2>
            <p className="mt-2 text-sm text-muted-foreground">
                Cadangan mencakup database serta bukti privat. Jadwal harian pukul {status.time} WIB
                berjalan pada server yang sudah dikonfigurasi.
            </p>
            {status.error && (
                <p role="alert" className="mt-4 text-sm text-destructive">
                    {status.error}
                </p>
            )}
            <p className="mt-4 text-sm">
                Retensi: {status.retention.daily} harian dan {status.retention.weekly} mingguan.
                Ubah melalui pengaturan retensi di atas. Cadangan manual dan sebelum pemulihan
                dipertahankan.
            </p>
            <form onSubmit={create} className="mt-6 space-y-3">
                <Label htmlFor="backup-password">Kata sandi saat ini</Label>
                <Input
                    id="backup-password"
                    type="password"
                    autoComplete="current-password"
                    value={backup.data.current_password}
                    onChange={(event) => backup.setData('current_password', event.target.value)}
                    required
                />
                <InputError message={backup.errors.current_password} />
                <InputError message={(backup.errors as Record<string, string>).backup} />
                <Button disabled={backup.processing || !!status.error}>
                    {backup.processing ? 'Membuat cadangan...' : 'Buat cadangan sekarang'}
                </Button>
            </form>
            <ul className="mt-6 divide-y border-t">
                {status.files.map((file) => (
                    <li
                        key={file.id}
                        className="flex flex-wrap items-center justify-between gap-3 py-4"
                    >
                        <div>
                            <p className="text-sm font-medium">
                                {new Date(file.created_at).toLocaleString('id-ID', {
                                    timeZone: 'Asia/Jakarta',
                                })}{' '}
                                WIB
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {(file.bytes / 1024 / 1024).toFixed(2)} MiB · Database dan bukti
                            </p>
                        </div>
                        <a
                            className="text-sm font-medium underline underline-offset-4"
                            href={route('backups.download', { backup: file.id })}
                        >
                            Unduh cadangan
                        </a>
                    </li>
                ))}
                {!status.files.length && (
                    <li className="py-4 text-sm text-muted-foreground">
                        Belum ada cadangan pada lokasi server ini.
                    </li>
                )}
            </ul>
            <p className="mt-4 text-sm text-muted-foreground">
                Unduhan memerlukan konfirmasi password. Pemulihan dilakukan pengelola pada server
                dalam maintenance mode, setelah membuat cadangan kondisi sekarang. Aplikasi tetap
                ditutup sampai rekonsiliasi selesai.
            </p>
        </section>
    );
}
