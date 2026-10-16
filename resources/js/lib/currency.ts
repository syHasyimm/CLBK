export const rupiah = (value: number | null | undefined) =>
    value == null
        ? '—'
        : new Intl.NumberFormat('id-ID', {
              style: 'currency',
              currency: 'IDR',
              maximumFractionDigits: 0,
          }).format(value);
