import useSaleForm from '@/features/sales/hooks/use-sale-form';
import type { DriverVehicleAssignment, ReferenceOption } from '@/types/operations';
import type { SaleKind, SaleRecord } from '@/types/sales';
import FactorySaleEntryForm from './FactorySaleEntryForm';
import SortSaleEntryForm from './SortSaleEntryForm';

export interface SaleFormProps {
    kind: SaleKind;
    replacement: SaleRecord | null;
    selectedVehicle: ReferenceOption | null;
    selectedFactory: ReferenceOption | null;
    vehicleAssignment: DriverVehicleAssignment | null;
}

export default function SaleForm(props: SaleFormProps) {
    const model = useSaleForm(props);
    return model.factorySale ? (
        <FactorySaleEntryForm model={model} />
    ) : (
        <SortSaleEntryForm model={model} />
    );
}
