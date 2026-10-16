import type { SaleFormProps } from '@/features/sales/components/SaleForm';
import useSalePreview from '@/features/sales/hooks/use-sale-preview';
import { requestKey } from '@/lib/idempotency';
import { kgInput } from '@/lib/weight';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function useSaleForm({
    kind,
    replacement,
    selectedVehicle,
    selectedFactory,
    vehicleAssignment,
}: SaleFormProps) {
    const factorySale = kind === 'factory';
    const [vehicle, setVehicle] = useState(selectedVehicle);
    const [factory, setFactory] = useState(selectedFactory);
    const form = useForm({
        vehicle_id: String(selectedVehicle?.id ?? ''),
        vehicle_assignment_version: vehicleAssignment?.version ?? null,
        factory_id: String(selectedFactory?.id ?? ''),
        accepted_kg: replacement?.accepted_grams ? kgInput(replacement.accepted_grams) : '',
        sold_kg: replacement?.manual_sold_grams ? kgInput(replacement.manual_sold_grams) : '',
        sell_price_snapshot_idr:
            !factorySale && replacement ? String(replacement.sell_price_snapshot_idr) : '',
        invoice_number: '',
        deduction_idr: String(replacement?.deduction_idr ?? 0),
        buyer_name: replacement?.buyer_name ?? '',
        buyer_contact: replacement?.buyer_contact ?? '',
        notes: '',
        replaces_sale_id: replacement?.id ?? null,
        correction_reason: '',
        factory_price_schedule_id: null as number | null,
        idempotency_key: requestKey(),
    });
    const summary = useSalePreview(
        kind,
        factorySale
            ? {
                  factory_id: form.data.factory_id,
                  accepted_kg: form.data.accepted_kg || '0',
                  deduction_idr: form.data.deduction_idr || '0',
              }
            : {
                  sold_kg: form.data.sold_kg || '0',
                  sell_price_snapshot_idr: form.data.sell_price_snapshot_idr || '0',
              },
    );
    const ready =
        !!vehicle &&
        !vehicleAssignment?.error &&
        (!factorySale || !!factory) &&
        summary.ready &&
        !form.processing;
    function submit(e: FormEvent) {
        e.preventDefault();
        if (!ready) return;
        form.transform((data) => ({
            vehicle_id: data.vehicle_id,
            ...(vehicleAssignment
                ? { vehicle_assignment_version: data.vehicle_assignment_version }
                : {}),
            notes: data.notes,
            replaces_sale_id: data.replaces_sale_id,
            correction_reason: data.correction_reason,
            idempotency_key: data.idempotency_key,
            ...(factorySale
                ? {
                      factory_id: data.factory_id,
                      accepted_kg: data.accepted_kg,
                      invoice_number: data.invoice_number,
                      deduction_idr: data.deduction_idr || '0',
                      factory_price_schedule_id: summary.data?.price.id,
                  }
                : {
                      sold_kg: data.sold_kg,
                      sell_price_snapshot_idr: data.sell_price_snapshot_idr,
                      buyer_name: data.buyer_name,
                      buyer_contact: data.buyer_contact,
                  }),
        }));
        form.post(route(kind + '-sales.store'), {
            onError: (errors) => {
                if (errors.factory_price_schedule_id) summary.refresh();
            },
        });
    }
    return {
        kind,
        replacement,
        vehicleAssignment,
        factorySale,
        vehicle,
        setVehicle,
        factory,
        setFactory,
        form,
        summary,
        ready,
        submit,
    };
}

export type SaleFormModel = ReturnType<typeof useSaleForm>;
