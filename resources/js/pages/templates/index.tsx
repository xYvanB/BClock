import { FlashMessage } from '@/components/flash-message';
import { TemplateFormSheet } from '@/components/template-form-sheet';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type AreaOption, type BreadcrumbItem, type TemplateListItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Template', href: '/templates' }];

export default function TemplatesIndex({ templates, areas }: { templates: TemplateListItem[]; areas: AreaOption[] }) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [editing, setEditing] = useState<TemplateListItem | null>(null);

    const openCreate = () => {
        setEditing(null);
        setSheetOpen(true);
    };

    const openEdit = (template: TemplateListItem) => {
        setEditing(template);
        setSheetOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Template" />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Template</h1>
                    <Button onClick={openCreate}>
                        <Plus /> Nuovo template
                    </Button>
                </div>

                {templates.length === 0 ? (
                    <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">Nessun template – crea il primo</p>
                        <Button onClick={openCreate}>Crea template</Button>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">Attività</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {templates.map((template) => (
                                    <tr key={template.id} className="border-sidebar-border/70 border-t">
                                        <td className="px-4 py-3 font-medium">{template.name}</td>
                                        <td className="px-4 py-3">{template.activities.length}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => openEdit(template)}
                                                aria-label={`Modifica ${template.name}`}
                                            >
                                                <Pencil /> Modifica
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <TemplateFormSheet open={sheetOpen} template={editing} areas={areas} onClose={() => setSheetOpen(false)} />
        </AppLayout>
    );
}
