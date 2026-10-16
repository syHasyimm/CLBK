export const weight = (grams: number) =>
    new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(grams / 1000) + ' kg';
export const kgInput = (grams: number) => (grams / 1000).toFixed(3);
export const kg = (grams: number) =>
    new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(grams / 1000);
