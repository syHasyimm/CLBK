import type { WeighingFormProps } from '@/features/weighings/components/WeighingForm';
import useWeighingPreview from '@/features/weighings/hooks/use-weighing-preview';
import { requestKey } from '@/lib/idempotency';
import type { ReferenceOption } from '@/types/operations';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function useWeighingForm({
    selectedCustomer,
    availableWorkers,
    selectedVehicle,
    replacement,
    vehicleAssignment,
}: WeighingFormProps) {
    const role = usePage().props.auth.user.role;
    const manager = role === 'manager';
    const driver = role === 'driver';
    const [customer, setCustomer] = useState<ReferenceOption | null>(
        replacement?.customer ?? selectedCustomer,
    );
    const [vehicle, setVehicle] = useState(selectedVehicle);
    const [workers, setWorkers] = useState<ReferenceOption[]>([]);
    const form = useForm({
        customer_id: customer ? String(customer.id) : '',
        vehicle_id: vehicle ? String(vehicle.id) : '',
        vehicle_assignment_version: vehicleAssignment?.version ?? null,
        tbs_kg: '',
        sort_kg: '0',
        worker_ids: [] as number[],
        replaces_weighing_id: replacement?.id ?? null,
        idempotency_key: requestKey(),
    });
    const summary = useWeighingPreview(form.data.tbs_kg, form.data.sort_kg, form.data.worker_ids);
    const errors = form.errors as Record<string, string>;
    const workerError =
        errors.worker_ids ??
        summary.errors.worker_ids ??
        Object.entries(errors).find(([key]) => key.startsWith('worker_ids.'))?.[1] ??
        Object.entries(summary.errors).find(([key]) => key.startsWith('worker_ids.'))?.[1];
    const names = Object.fromEntries(workers.map((worker) => [worker.id, worker.label]));
    function updateWorkers(selected: ReferenceOption[]) {
        setWorkers(selected);
        form.setData(
            'worker_ids',
            selected.map((worker) => worker.id),
        );
    }
    function submit(e?: FormEvent) {
        e?.preventDefault();
        if (
            !summary.ready ||
            !summary.preview ||
            !vehicle ||
            vehicleAssignment?.error ||
            form.processing
        )
            return;
        form.transform((data) => {
            const { replaces_weighing_id, vehicle_assignment_version, ...rest } = data;
            return {
                ...rest,
                ...(manager ? { replaces_weighing_id } : {}),
                ...(vehicleAssignment ? { vehicle_assignment_version } : {}),
                tbs_kg: data.tbs_kg || '0',
                sort_kg: data.sort_kg || '0',
                price_schedule_id: summary.price!.id,
            };
        });
        form.post(route('weighings.store'), {
            preserveScroll: true,
            onError: (messages) => {
                if (messages.price_schedule_id) summary.refresh();
            },
        });
    }
    return {
        availableWorkers,
        replacement,
        vehicleAssignment,
        manager,
        driver,
        customer,
        setCustomer,
        vehicle,
        setVehicle,
        workers,
        form,
        summary,
        errors,
        workerError,
        names,
        updateWorkers,
        submit,
    };
}

export type WeighingFormModel = ReturnType<typeof useWeighingForm>;
