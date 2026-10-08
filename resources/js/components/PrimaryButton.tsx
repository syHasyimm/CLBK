import { Button } from '@/components/ui/button';
import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton(props: ButtonHTMLAttributes<HTMLButtonElement>) {
    return <Button type="submit" {...props} />;
}
