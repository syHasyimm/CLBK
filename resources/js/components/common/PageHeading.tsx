import { ReactNode } from 'react';
export default function PageHeading({
    title,
    description,
    action,
}: {
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-5">
            <div>
                <p className="mb-2 text-xs font-medium uppercase tracking-widest text-muted-foreground">
                    Pengelolaan usaha
                </p>
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{title}</h1>
                <p className="mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground">
                    {description}
                </p>
            </div>
            {action}
        </div>
    );
}
