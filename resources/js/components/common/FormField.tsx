import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { NativeSelect } from '@/components/ui/native-select';
import InputError from '@/components/InputError';

export interface FieldDefinition {
    key: string;
    label: string;
    type: string;
    required: boolean;
    maxLength?: number | null;
}
export interface SelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}
export default function FormField({
    field,
    value,
    error,
    options = [],
    onChange,
}: {
    field: FieldDefinition;
    value: string;
    error?: string;
    options?: SelectOption[];
    onChange: (value: string) => void;
}) {
    const shared = {
        id: field.key,
        name: field.key,
        value,
        required: field.required,
        'aria-invalid': !!error,
        'aria-describedby': error ? field.key + '-error' : undefined,
    };
    return (
        <div className={field.type === 'textarea' ? 'sm:col-span-2' : undefined}>
            <Label htmlFor={field.key}>
                {field.label}
                {!field.required && (
                    <span className="font-normal text-muted-foreground"> (opsional)</span>
                )}
            </Label>
            <div className="mt-2">
                {field.type === 'textarea' ? (
                    <Textarea
                        {...shared}
                        maxLength={field.maxLength ?? undefined}
                        onChange={(e) => onChange(e.target.value)}
                    />
                ) : field.type === 'select' ? (
                    <NativeSelect {...shared} onChange={(e) => onChange(e.target.value)}>
                        <option value="">
                            {field.required
                                ? 'Pilih ' + field.label.toLowerCase()
                                : 'Tanpa ' + field.label.toLowerCase()}
                        </option>
                        {options.map((option) => (
                            <option
                                key={option.value}
                                value={option.value}
                                disabled={option.disabled}
                            >
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                ) : (
                    <Input
                        {...shared}
                        type={field.type === 'weight' ? 'text' : field.type}
                        step={field.type === 'datetime-local' ? 1 : undefined}
                        inputMode={field.type === 'weight' ? 'decimal' : undefined}
                        maxLength={field.maxLength ?? undefined}
                        placeholder={field.type === 'weight' ? 'Contoh: 8000,500' : undefined}
                        autoComplete={
                            field.type === 'password'
                                ? 'new-password'
                                : field.type === 'email'
                                  ? 'email'
                                  : 'off'
                        }
                        onChange={(e) => onChange(e.target.value)}
                    />
                )}
            </div>
            <InputError id={field.key + '-error'} message={error} className="mt-2" />
        </div>
    );
}
