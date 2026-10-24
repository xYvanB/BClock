export const statusOptions: { value: string; label: string }[] = [
    { value: 'da_fare', label: 'Da fare' },
    { value: 'in_corso', label: 'In corso' },
    { value: 'in_attesa_di_cliente', label: 'In attesa di cliente' },
    { value: 'completato', label: 'Completato' },
    { value: 'archiviato', label: 'Archiviato' },
];

export const statusLabel = (status: string): string => statusOptions.find((option) => option.value === status)?.label ?? status;
