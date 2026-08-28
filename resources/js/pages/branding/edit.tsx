import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface Watermark {
    enabled: boolean;
    image_url: string | null;
    position: string;
    size: number;
    opacity: number;
}

interface BrandingEditProps {
    logo_url: string | null;
    hide_attribution: boolean;
    watermark: Watermark;
    watermark_positions: string[];
}

type Tab = 'logo' | 'watermark' | 'attribution';

export default function BrandingEdit({ logo_url, hide_attribution, watermark, watermark_positions }: BrandingEditProps) {
    const { t } = useTranslation();
    const { capabilities } = usePage<SharedData>().props;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const watermarkInputRef = useRef<HTMLInputElement>(null);
    const [tab, setTab] = useState<Tab>('logo');

    // Taking "Powered by ProjectSend" off the pages this installation
    // serves is the white-label half, and it stays a hosted feature: the
    // tab appears only where the capability is held, and the route that
    // saves it is registered by the cloud-modules package rather than
    // here. An installation without that package has no listener able to
    // hide anything, so offering the switch would be offering a control
    // that does nothing.
    const canHideAttribution = capabilities.includes('attribution.hide');
    const tabs: Tab[] = canHideAttribution ? ['logo', 'watermark', 'attribution'] : ['logo', 'watermark'];

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Branding'), href: '/system/settings/branding' }];

    const uploadForm = useForm<{ logo: File | null }>({ logo: null });
    const removeForm = useForm({});

    const watermarkForm = useForm<{
        image: File | null;
        enabled: boolean;
        position: string;
        size: number;
        opacity: number;
    }>({
        image: null,
        enabled: watermark.enabled,
        position: watermark.position,
        size: watermark.size,
        opacity: watermark.opacity,
    });

    const removeWatermarkForm = useForm({});

    const attributionForm = useForm({ hide_attribution });

    // Debounced so dragging a number field is one request when it settles
    // rather than one per keystroke — each costs a real GD render on the
    // server. The URL is the whole state, so the browser handles the rest:
    // a changed src loads, an unchanged one is a no-op.
    const [sampleSettings, setSampleSettings] = useState({
        position: watermark.position,
        size: watermark.size,
        opacity: watermark.opacity,
    });

    const { position, size, opacity } = watermarkForm.data;

    useEffect(() => {
        const timer = setTimeout(() => setSampleSettings({ position, size, opacity }), 300);

        return () => clearTimeout(timer);
    }, [position, size, opacity]);

    // `image_url` in the key so replacing the artwork and saving busts the
    // browser's cache for what is otherwise an identical URL.
    const sampleUrl = `${route('branding.watermark.sample')}?${new URLSearchParams({
        position: sampleSettings.position,
        size: String(sampleSettings.size),
        opacity: String(sampleSettings.opacity),
        v: watermark.image_url ?? '',
    })}`;

    // Keyed by the value the server stores, so the labels and the nine-cell
    // grid below can never drift apart from the enum they describe.
    const positionLabels: Record<string, string> = {
        'top-left': t('Top left'),
        'top-center': t('Top centre'),
        'top-right': t('Top right'),
        'middle-left': t('Middle left'),
        center: t('Centre'),
        'middle-right': t('Middle right'),
        'bottom-left': t('Bottom left'),
        'bottom-center': t('Bottom centre'),
        'bottom-right': t('Bottom right'),
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        uploadForm.post(route('branding.store'), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                uploadForm.reset();
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const remove = () => {
        removeForm.delete(route('branding.destroy'), { preserveScroll: true, preserveState: true });
    };

    const submitWatermark: FormEventHandler = (e) => {
        e.preventDefault();
        watermarkForm.post(route('branding.watermark.update'), {
            // Always multipart: the payload carries a file on some saves and
            // not others, and a boolean posted as form data would otherwise
            // arrive as the string "false" on the saves that do.
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                watermarkForm.setData('image', null);
                if (watermarkInputRef.current) watermarkInputRef.current.value = '';
            },
        });
    };

    const removeWatermark = () => {
        removeWatermarkForm.delete(route('branding.watermark.destroy'), { preserveScroll: true, preserveState: true });
    };

    const submitAttribution: FormEventHandler = (e) => {
        e.preventDefault();
        attributionForm.patch(route('branding.attribution.update'), { preserveScroll: true, preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Branding')} />

            <div className="px-4 py-6">
                <Heading title={t('Branding')} description={t('Your own artwork on this installation.')} />

                <nav className="flex gap-1 border-b">
                    {tabs.map((tabKey) => (
                        <button
                            type="button"
                            key={tabKey}
                            onClick={() => setTab(tabKey)}
                            className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            {tabKey === 'logo' ? t('Logo') : tabKey === 'watermark' ? t('Watermark') : t('Attribution')}
                        </button>
                    ))}
                </nav>

                <div className="mt-6 max-w-md">
                    {/* No heading repeating the tab's own label — the tab is
                        the heading. Same shape as the theming screen. */}
                    <section className={`space-y-6 ${tab === 'logo' ? '' : 'hidden'}`}>
                        <p className="text-muted-foreground text-sm">
                            {t('Show your own logo in the sidebar instead of the default icon.')}
                        </p>

                        <div className="space-y-2">
                            {logo_url ? (
                                <img src={logo_url} alt="" className="h-16 max-w-full rounded border object-contain p-2" />
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('No custom logo set — the default icon is shown.')}</p>
                            )}
                        </div>

                        <form onSubmit={submit} className="space-y-2">
                            <Input
                                ref={fileInputRef}
                                type="file"
                                accept="image/*"
                                onChange={(e) => uploadForm.setData('logo', e.target.files?.[0] ?? null)}
                            />
                            <InputError message={uploadForm.errors.logo} />
                            <Button type="submit" disabled={uploadForm.processing || uploadForm.data.logo === null}>
                                {t('Upload logo')}
                            </Button>
                        </form>

                        {logo_url && (
                            <Button variant="outline" onClick={remove} disabled={removeForm.processing}>
                                {t('Remove logo')}
                            </Button>
                        )}
                    </section>

                    {/* Both panels stay mounted and the inactive one is just
                        hidden, unlike the theming screen's tabs: these are
                        forms, and switching tabs must not throw away a file
                        someone has already picked or a number they have
                        already typed. */}
                    <section className={`space-y-6 ${tab === 'watermark' ? '' : 'hidden'}`}>
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Stamp an image over the thumbnails and previews clients and public visitors see. What staff see in the file manager is left unmarked, and the original files — including every download — are never altered.',
                            )}
                        </p>

                        <form onSubmit={submitWatermark} className="space-y-6">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="watermark_enabled"
                                    checked={watermarkForm.data.enabled}
                                    onCheckedChange={(checked) => watermarkForm.setData('enabled', checked === true)}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="watermark_enabled">{t('Watermark what clients and visitors see')}</Label>
                                    <p className="text-muted-foreground text-sm">
                                        {t('Applies to thumbnails and previews. Existing ones are rebuilt the next time they are viewed.')}
                                    </p>
                                </div>
                            </div>
                            <InputError message={watermarkForm.errors.enabled} />

                            {/* Staff never see a real watermark — every staff
                                surface is deliberately unmarked — so without this
                                the only way to judge these settings would be to
                                sign in as a client. Rendered server-side by the
                                same painter the real thing uses, so it cannot
                                flatter the settings. */}
                            <div className="grid gap-2">
                                <Label>{t('What clients will see')}</Label>

                                {watermark.image_url ? (
                                    <>
                                        <img
                                            src={sampleUrl}
                                            alt={t('A sample image with the watermark applied')}
                                            width={480}
                                            height={300}
                                            className="w-full max-w-md rounded border"
                                        />
                                        <p className="text-muted-foreground text-sm">
                                            {t('A stand-in image, not one of your files. Choosing a new watermark above updates this once you save.')}
                                        </p>
                                    </>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        {t('Upload a watermark image below to see a preview here.')}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="watermark_image">{t('Watermark image')}</Label>

                                {watermark.image_url && (
                                    <img
                                        src={watermark.image_url}
                                        alt=""
                                        className="bg-muted h-16 max-w-full rounded border object-contain p-2"
                                    />
                                )}

                                <Input
                                    id="watermark_image"
                                    ref={watermarkInputRef}
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => watermarkForm.setData('image', e.target.files?.[0] ?? null)}
                                />
                                <p className="text-muted-foreground text-sm">
                                    {watermark.image_url
                                        ? t('Choose a file only to replace the image above.')
                                        : t('A PNG with a transparent background works best.')}
                                </p>
                                <InputError message={watermarkForm.errors.image} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="watermark_position">{t('Position')}</Label>

                                <Select
                                    value={watermarkForm.data.position}
                                    onValueChange={(value) => watermarkForm.setData('position', value)}
                                >
                                    <SelectTrigger id="watermark_position" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {watermark_positions.map((position) => (
                                            <SelectItem key={position} value={position}>
                                                {positionLabels[position] ?? position}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {/* The nine positions as they actually sit on a thumbnail — a
                                    dropdown alone makes "middle left" and "bottom centre" a
                                    reading exercise. Clicking a cell is the same choice as
                                    picking from the list above. */}
                                <div
                                    className="bg-muted/40 mt-1 grid aspect-[4/3] w-40 grid-cols-3 grid-rows-3 gap-1 rounded border p-1"
                                    role="group"
                                    aria-label={t('Position')}
                                >
                                    {watermark_positions.map((position) => (
                                        <button
                                            key={position}
                                            type="button"
                                            title={positionLabels[position] ?? position}
                                            aria-label={positionLabels[position] ?? position}
                                            aria-pressed={watermarkForm.data.position === position}
                                            onClick={() => watermarkForm.setData('position', position)}
                                            className={`rounded-sm transition-colors ${
                                                watermarkForm.data.position === position
                                                    ? 'bg-primary'
                                                    : 'bg-background hover:bg-accent border'
                                            }`}
                                        />
                                    ))}
                                </div>

                                <InputError message={watermarkForm.errors.position} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="watermark_size">{t('Size (% of the image)')}</Label>
                                <Input
                                    id="watermark_size"
                                    type="number"
                                    min={5}
                                    max={100}
                                    className="max-w-32"
                                    value={watermarkForm.data.size}
                                    onChange={(e) => watermarkForm.setData('size', Number(e.target.value))}
                                />
                                <p className="text-muted-foreground text-sm">
                                    {t('The watermark is scaled to fit inside this share of whatever it is drawn on, keeping its proportions — so a thumbnail and a preview look like the same design.')}
                                </p>
                                <InputError message={watermarkForm.errors.size} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="watermark_opacity">{t('Opacity (%)')}</Label>
                                <Input
                                    id="watermark_opacity"
                                    type="number"
                                    min={1}
                                    max={100}
                                    className="max-w-32"
                                    value={watermarkForm.data.opacity}
                                    onChange={(e) => watermarkForm.setData('opacity', Number(e.target.value))}
                                />
                                <InputError message={watermarkForm.errors.opacity} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={watermarkForm.processing}>
                                    {t('Save watermark settings')}
                                </Button>

                                {watermarkForm.recentlySuccessful && <p className="text-muted-foreground text-sm">{t('Saved.')}</p>}
                            </div>
                        </form>

                        {watermark.image_url && (
                            <Button variant="outline" onClick={removeWatermark} disabled={removeWatermarkForm.processing}>
                                {t('Remove watermark')}
                            </Button>
                        )}
                    </section>

                    <section className={`space-y-6 ${canHideAttribution && tab === 'attribution' ? '' : 'hidden'}`}>
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'By default a small "Powered by ProjectSend" line appears on the public pages, in the client portal and at the foot of outgoing email. Turn it off to leave no trace of the software your clients are using.',
                            )}
                        </p>

                        <form onSubmit={submitAttribution} className="space-y-4">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="hide_attribution"
                                    checked={attributionForm.data.hide_attribution}
                                    onCheckedChange={(checked) => attributionForm.setData('hide_attribution', checked === true)}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="hide_attribution">{t('Hide "Powered by ProjectSend"')}</Label>
                                    <p className="text-muted-foreground text-sm">
                                        {t('Your own staff still see the version and licence on the About screen.')}
                                    </p>
                                </div>
                            </div>
                            <InputError message={attributionForm.errors.hide_attribution} />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={attributionForm.processing}>
                                    {t('Save attribution settings')}
                                </Button>

                                {attributionForm.recentlySuccessful && <p className="text-muted-foreground text-sm">{t('Saved.')}</p>}
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
