import FormField from '@/components/common/FormField';
import type { SaleFormModel } from '@/features/sales/hooks/use-sale-form';
export default function SaleFormField({
    model,
    name: key,
    label,
    type = 'text',
    required = true,
}: {
    model: SaleFormModel;
    name: keyof SaleFormModel['form']['data'];
    label: string;
    type?: string;
    required?: boolean;
}) {
    const { form, summary } = model;
    return (
        <FormField
            field={{
                key,
                label,
                type,
                required,
                maxLength: type === 'textarea' ? 1000 : undefined,
            }}
            value={String(form.data[key] ?? '')}
            error={form.errors[key] || summary.errors[key]}
            onChange={(value) => form.setData(key, value as never)}
        />
    );
}
