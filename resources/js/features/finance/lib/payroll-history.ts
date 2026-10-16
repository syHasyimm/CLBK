export const dateLabel = (value?: string) =>
    value
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'UTC' }).format(
              new Date(`${value.slice(0, 10)}T00:00:00Z`),
          )
        : '—';
