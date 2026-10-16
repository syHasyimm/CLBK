import { PropsWithChildren, ReactNode } from 'react';
import StaffLayout from './StaffLayout';

export default function AppLayout({ header, children }: PropsWithChildren<{ header?: ReactNode }>) {
    return <StaffLayout header={header}>{children}</StaffLayout>;
}
