import AssignedVehicle from '@/components/common/AssignedVehicle';
import ReferencePicker from '@/components/common/ReferencePicker';

import type { SaleFormModel } from '@/features/sales/hooks/use-sale-form';

export default function SaleVehiclePicker({ model }: { model: SaleFormModel }) {
    const { vehicleAssignment, vehicle, setVehicle, form } = model;

    return (
        <>
            {vehicleAssignment ? (
                <AssignedVehicle
                    vehicle={vehicle}
                    blocked={vehicleAssignment.error}
                    error={form.errors.vehicle_id || form.errors.vehicle_assignment_version}
                />
            ) : (
                <ReferencePicker
                    id="vehicle_id"
                    label="Mobil"
                    url={route('operational-references.index', 'vehicles')}
                    value={vehicle}
                    error={form.errors.vehicle_id}
                    onChange={(value) => {
                        setVehicle(value);
                        form.setData('vehicle_id', String(value?.id ?? ''));
                    }}
                />
            )}
        </>
    );
}
