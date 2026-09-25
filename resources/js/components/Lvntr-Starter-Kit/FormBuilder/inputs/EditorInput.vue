<script setup lang="ts">
    import { computed, onBeforeUnmount, ref, watch } from 'vue';
    import { Button, ButtonGroup } from 'primevue';
    import Tooltip from 'primevue/tooltip';

    const vTooltip = Tooltip;
    import { useEditor, EditorContent } from '@tiptap/vue-3';
    import { BubbleMenu } from '@tiptap/vue-3/menus';
    import StarterKit from '@tiptap/starter-kit';
    import Image from '@tiptap/extension-image';
    import Placeholder from '@tiptap/extension-placeholder';
    import TextAlign from '@tiptap/extension-text-align';
    import { Table } from '@tiptap/extension-table';
    import { TableRow } from '@tiptap/extension-table-row';
    import { TableHeader } from '@tiptap/extension-table-header';
    import { TableCell } from '@tiptap/extension-table-cell';
    import { Color } from '@tiptap/extension-color';
    import { TextStyle, BackgroundColor, FontFamily, FontSize, LineHeight } from '@tiptap/extension-text-style';
    import { TaskList, TaskItem } from '@tiptap/extension-list';
    import Subscript from '@tiptap/extension-subscript';
    import Superscript from '@tiptap/extension-superscript';
    import Youtube from '@tiptap/extension-youtube';
    import Link from '@tiptap/extension-link';
    import type { Extensions } from '@tiptap/core';
    import InputText from 'primevue/inputtext';
    import Popover from 'primevue/popover';
    import Select from 'primevue/select';
    import SelectButton from 'primevue/selectbutton';
    import EditorColorPalette from './EditorColorPalette.vue';
    import type { EditorImageUploadConfig, EditorToolbarPreset } from '../core';
    import type { FileItem } from '../../FileManager/types';
    import EditorImagePicker from './EditorImagePicker.vue';
    import { useDialog } from '@/composables/useDialog';
    import { getXsrfToken } from '@/composables/useCsrf';
    import { withBasePath } from '@/composables/useBasePath';
    import { useToast } from 'primevue/usetoast';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        id?: string;
        /** Id of the element naming the editor — a contenteditable cannot be a `label[for]` target. */
        ariaLabelledby?: string;
        ariaRequired?: boolean;
        modelValue: string;
        placeholder?: string;
        toolbar?: EditorToolbarPreset;
        minHeight?: string;
        imageUpload?: EditorImageUploadConfig;
        links?: boolean;
        treatEmptyAsBlank?: boolean;
        disabled?: boolean;
        invalid?: boolean;
    }

    const props = withDefaults(defineProps<Props>(), {
        id: undefined,
        ariaLabelledby: undefined,
        ariaRequired: false,
        placeholder: undefined,
        toolbar: 'standard',
        minHeight: '10rem',
        imageUpload: undefined,
        links: false,
        treatEmptyAsBlank: true,
        disabled: false,
        invalid: false,
    });

    const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

    const defaultAcceptedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const acceptedMimes = computed(() => props.imageUpload?.acceptedMimes ?? defaultAcceptedMimes);

    type TableBorderStyle = 'none' | 'light' | 'normal' | 'bold' | 'primary';

    function tableClassFor(style: TableBorderStyle): string {
        return `sk-rte__table sk-rte__table--${style}`;
    }

    function parseTableBorderStyle(className: string | null | undefined): TableBorderStyle {
        const match = (className ?? '').match(/sk-rte__table--(none|light|normal|bold|primary)/);
        return (match?.[1] as TableBorderStyle) ?? 'light';
    }

    const CustomTable = Table.extend({
        addAttributes() {
            return {
                ...this.parent?.(),
                class: {
                    default: tableClassFor('light'),
                    parseHTML: (el) => el.getAttribute('class') ?? tableClassFor('light'),
                    renderHTML: (attrs) => ({
                        class: (attrs.class as string) ?? tableClassFor('light'),
                    }),
                },
                borderColor: {
                    default: null,
                    parseHTML: (el) => {
                        const styleValue = el.getAttribute('style') ?? '';
                        return styleValue.match(/--sk-table-border:\s*([^;]+)/i)?.[1]?.trim() ?? null;
                    },
                    renderHTML: (attrs) =>
                        attrs.borderColor ? { style: `--sk-table-border: ${attrs.borderColor as string}` } : {},
                },
            };
        },
    });

    const CustomImage = Image.extend({
        addAttributes() {
            return {
                ...this.parent?.(),
                width: {
                    default: null,
                    parseHTML: (el) => {
                        const styleWidth = (el.getAttribute('style') ?? '').match(/width:\s*([^;]+)/i)?.[1]?.trim();
                        return styleWidth ?? el.getAttribute('width');
                    },
                    renderHTML: (attrs) => (attrs.width ? { style: `width: ${attrs.width as string}` } : {}),
                },
                'data-align': {
                    default: null,
                    parseHTML: (el) => el.getAttribute('data-align'),
                    renderHTML: (attrs) => (attrs['data-align'] ? { 'data-align': attrs['data-align'] as string } : {}),
                },
            };
        },
    });

    // A button is a link with a style: `<a href data-sk-button="primary" data-sk-color="#hex">`.
    // Renderers that know the attributes (the kit CSS, a mobile app) draw a
    // button; everything else still gets a working link. The inline CSS
    // variables are the web copy of data-sk-color — CSS cannot read a data
    // attribute as a color.
    const buttonVariants = ['primary', 'secondary', 'outline'] as const;
    type ButtonVariant = (typeof buttonVariants)[number];
    const hexColor = /^#[0-9a-f]{6}$/i;

    function readableTextOn(hex: string): string {
        const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
        return 0.299 * r + 0.587 * g + 0.114 * b > 160 ? '#111827' : '#ffffff';
    }

    const ButtonLink = Link.extend({
        addAttributes() {
            return {
                ...this.parent?.(),
                button: {
                    default: null,
                    parseHTML: (el) => {
                        const value = el.getAttribute('data-sk-button');
                        return buttonVariants.includes(value as ButtonVariant) ? value : null;
                    },
                    renderHTML: (attrs) => (attrs.button ? { 'data-sk-button': attrs.button as string } : {}),
                },
                buttonColor: {
                    default: null,
                    parseHTML: (el) => {
                        const value = el.getAttribute('data-sk-color') ?? '';
                        return hexColor.test(value) ? value.toLowerCase() : null;
                    },
                    renderHTML: (attrs) => {
                        const color = attrs.buttonColor as string | null;
                        if (!color) return {};
                        return {
                            'data-sk-color': color,
                            style: `--sk-button-color: ${color}; --sk-button-text: ${readableTextOn(color)}`,
                        };
                    },
                },
            };
        },
    });

    const extensions: Extensions = [
        // StarterKit v3 bundles Link; it is swapped for ButtonLink below, so the
        // bundled one stays off (two would warn "Duplicate extension names").
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: false,
        }),
        Placeholder.configure({ placeholder: () => props.placeholder ?? '' }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
            alignments: ['left', 'center', 'right', 'justify'],
        }),
        CustomTable.configure({ resizable: true }),
        TableRow,
        TableHeader,
        TableCell,
        TextStyle,
        Color.configure({ types: ['textStyle'] }),
        BackgroundColor,
        FontFamily,
        FontSize,
        LineHeight,
        TaskList,
        TaskItem.configure({ nested: true }),
        Subscript,
        Superscript,
        // nocookie + no paste handler: the only way in is the toolbar prompt,
        // and HtmlSanitizer only keeps iframes pointing at the embed endpoint.
        Youtube.configure({
            nocookie: true,
            addPasteHandler: false,
            width: 640,
            height: 360,
        }),
    ];
    if (props.links) {
        extensions.push(ButtonLink.configure({ openOnClick: false, autolink: true }));
    }
    if (props.imageUpload) {
        extensions.push(CustomImage.configure({ inline: true, allowBase64: false }));
    }

    const uploadingImage = ref(false);
    const dialog = useDialog();
    const toast = useToast();

    /**
     * Attributes for the node the user actually types in.
     *
     * `<EditorContent>` renders a plain wrapper `<div>` and Tiptap puts the
     * contenteditable node INSIDE it, so an `id` or `aria-*` on the wrapper
     * names nothing: a `<div>` is not a labelable element, and assistive
     * technology reads the state of the editable node, not of its wrapper.
     * Everything that identifies or describes the control therefore goes here.
     *
     * Tiptap reads these once, at creation — every call site passes a value that
     * is fixed for the field (its key, its label id, its required flag).
     */
    const editableAttributes: Record<string, string> = {
        class: 'sk-rte__content',
        spellcheck: 'true',
        role: 'textbox',
        'aria-multiline': 'true',
    };

    if (props.id) {
        editableAttributes.id = props.id;
    }
    if (props.ariaLabelledby) {
        editableAttributes['aria-labelledby'] = props.ariaLabelledby;
    }
    if (props.ariaRequired) {
        editableAttributes['aria-required'] = 'true';
    }

    const editor = useEditor({
        extensions,
        content: props.modelValue,
        editable: !props.disabled,
        onUpdate: ({ editor }) => {
            const html = editor.getHTML();
            const out = props.treatEmptyAsBlank && editor.isEmpty ? '' : html;
            emit('update:modelValue', out);
        },
        editorProps: {
            attributes: editableAttributes,
            handlePaste: (_view, event) => handleImageEvent(event as unknown as ClipboardEvent, 'paste'),
            handleDrop: (_view, event) => handleImageEvent(event as unknown as DragEvent, 'drop'),
        },
    });

    watch(
        () => props.modelValue,
        (next) => {
            if (!editor.value || next === editor.value.getHTML()) return;
            // treatEmptyAsBlank echo of our own emit — nothing changed.
            if (!next && editor.value.isEmpty) return;
            editor.value.commands.setContent(next || '', { emitUpdate: false });
            // An external change (e.g. TranslatableInput switching locale) must
            // replace the source buffer too, or the next keystroke writes the old
            // text into the new value.
            if (sourceMode.value) {
                resyncingSource = true;
                sourceHtml.value = formatHtml(editor.value.getHTML());
                resyncingSource = false;
                droppedTags.value = [];
            }
        },
    );

    /**
     * Replicate onUpdate's emit logic after a manual setContent({ emitUpdate: false }).
     * Needed when we mutate editor content programmatically (image upload) but still
     * want the parent v-model to stay in sync — otherwise stale preview blob: URLs or
     * leftover error fragments end up in the submitted form payload.
     */
    function syncModelFromEditor(): void {
        if (!editor.value) return;
        const html = editor.value.getHTML();
        const out = props.treatEmptyAsBlank && editor.value.isEmpty ? '' : html;
        emit('update:modelValue', out);
    }

    watch(
        () => props.disabled,
        (d) => editor.value?.setEditable(!d),
    );

    onBeforeUnmount(() => {
        editor.value?.destroy();
    });

    function uploadFile(file: File): Promise<string> {
        return new Promise((resolve, reject) => {
            const cfg = props.imageUpload;
            if (!cfg) {
                reject(new Error('image upload not configured'));
                return;
            }
            const formData = new FormData();
            formData.append('context', cfg.context);
            if (cfg.contextId != null) formData.append('context_id', String(cfg.contextId));
            if (cfg.folderId) formData.append('folder_id', cfg.folderId);
            if (cfg.folderName) formData.append('folder_name', cfg.folderName);
            formData.append('files[]', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', withBasePath('/file-manager/files'));
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-XSRF-TOKEN', getXsrfToken());
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.withCredentials = true;

            xhr.onload = () => {
                if (xhr.status === 413) {
                    reject(new Error('sk-file-manager.errors.too_large'));
                    return;
                }
                try {
                    const envelope = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300) {
                        // public_url first: the src ends up persisted in the
                        // document, so it has to survive past the admin
                        // session. `url` is session-gated and only a fallback
                        // (private disk -- nothing publishable exists there).
                        const uploaded = envelope?.data?.files?.[0];
                        const url = uploaded?.public_url ?? uploaded?.url;
                        if (typeof url === 'string' && url.length > 0) {
                            resolve(url);
                        } else {
                            reject(new Error('invalid upload response'));
                        }
                    } else {
                        const firstError = Object.values(envelope?.errors ?? {})[0];
                        const msg = Array.isArray(firstError) ? firstError[0] : (firstError ?? envelope?.message);
                        reject(new Error(typeof msg === 'string' ? msg : `upload failed (${xhr.status})`));
                    }
                } catch {
                    reject(new Error(xhr.status === 0 ? 'network error' : `upload failed (${xhr.status})`));
                }
            };
            xhr.onerror = () => reject(new Error('network error'));
            xhr.send(formData);
        });
    }

    async function insertImageFromFile(file: File): Promise<void> {
        if (!props.imageUpload || !editor.value) return;
        if (!acceptedMimes.value.includes(file.type)) return;

        const previewUrl = URL.createObjectURL(file);
        editor.value
            .chain()
            .focus()
            .insertContent({
                type: 'image',
                attrs: { src: previewUrl, alt: file.name, width: defaultImageWidth },
            })
            .insertContent(' ')
            .run();
        uploadingImage.value = true;

        try {
            const finalUrl = await uploadFile(file);
            const html = (editor.value.getHTML() ?? '').split(previewUrl).join(finalUrl);
            editor.value.commands.setContent(html, { emitUpdate: false });
            syncModelFromEditor();
        } catch (err) {
            const cleaned = (editor.value.getHTML() ?? '').replace(
                new RegExp(`<img[^>]*src="${escapeRegex(previewUrl)}"[^>]*>`, 'g'),
                '',
            );
            editor.value.commands.setContent(cleaned, { emitUpdate: false });
            syncModelFromEditor();
            const raw = (err as Error).message ?? '';
            const detail =
                raw.startsWith('sk-') || raw.startsWith('validation.')
                    ? (() => {
                          const translated = trans(raw);
                          return translated === raw ? trans('sk-editor.image_upload_failed') : translated;
                      })()
                    : raw || trans('sk-editor.image_upload_failed');
            toast.add({
                severity: 'error',
                group: 'bc',
                summary: trans('sk-editor.image_upload_failed'),
                detail,
                life: 4000,
            });
        } finally {
            URL.revokeObjectURL(previewUrl);
            uploadingImage.value = false;
        }
    }

    function escapeRegex(input: string): string {
        return input.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function handleImageEvent(event: ClipboardEvent | DragEvent, kind: 'paste' | 'drop'): boolean {
        if (!props.imageUpload) return false;
        const files =
            kind === 'paste'
                ? Array.from((event as ClipboardEvent).clipboardData?.files ?? [])
                : Array.from((event as DragEvent).dataTransfer?.files ?? []);
        const image = files.find((f) => acceptedMimes.value.includes(f.type));
        if (!image) return false;
        event.preventDefault();
        void insertImageFromFile(image);
        return true;
    }

    function pickImage(): void {
        if (!props.imageUpload || !editor.value) return;
        dialog.open(
            EditorImagePicker,
            {
                context: props.imageUpload.context,
                contextId: props.imageUpload.contextId ?? null,
                folderId: props.imageUpload.folderId ?? null,
                acceptedMimes: acceptedMimes.value,
                onPick: (file: FileItem) => insertUploadedImage(file),
            },
            trans('sk-editor.picker_title'),
            { width: '720px' },
        );
    }

    const defaultImageWidth = '200px';

    function insertUploadedImage(file: FileItem): void {
        if (!editor.value) return;
        editor.value
            .chain()
            .focus()
            .insertContent({
                type: 'image',
                // public_url, not url -- see the upload path above.
                attrs: {
                    src: file.public_url ?? file.url,
                    alt: file.name,
                    width: defaultImageWidth,
                },
            })
            .insertContent(' ')
            .run();
    }

    function promptLink(): void {
        if (!editor.value) return;
        const current = editor.value.getAttributes('link').href as string | undefined;
        const url = window.prompt('URL', current ?? 'https://');
        if (url === null) return;
        if (url === '') {
            editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }

    const buttonPopover = ref<InstanceType<typeof Popover> | null>(null);
    const buttonForm = ref<{ label: string; href: string; variant: ButtonVariant; color: string | null }>({
        label: '',
        href: '',
        variant: 'primary',
        color: null,
    });
    // Nothing selected and no link under the caret: the button text is typed in the popover.
    const buttonNeedsLabel = ref(false);
    const buttonVariantOptions = computed(() =>
        buttonVariants.map((value) => ({ value, label: trans(`sk-editor.button_${value}`) })),
    );

    const currentButton = computed<ButtonVariant | null>(() => {
        if (!editor.value) return null;
        return (editor.value.getAttributes('link').button as ButtonVariant | null) ?? null;
    });

    function toggleButtonPopover(event: Event): void {
        if (!editor.value) return;
        const attrs = editor.value.getAttributes('link');
        buttonForm.value = {
            label: '',
            href: (attrs.href as string | undefined) ?? 'https://',
            variant: (attrs.button as ButtonVariant | null) ?? 'primary',
            color: (attrs.buttonColor as string | null) ?? null,
        };
        buttonNeedsLabel.value = editor.value.state.selection.empty && !editor.value.isActive('link');
        buttonPopover.value?.toggle(event);
    }

    function applyButton(): void {
        if (!editor.value) return;
        const href = buttonForm.value.href.trim();
        const label = buttonForm.value.label.trim();
        if (href === '' || (buttonNeedsLabel.value && label === '')) return;

        // Secondary is the neutral style — a color would be ignored, so it is not stored.
        const { variant, color } = buttonForm.value;
        const attrs = { href, button: variant, buttonColor: variant === 'secondary' ? null : color };
        const chain = editor.value.chain().focus();
        if (buttonNeedsLabel.value) {
            chain.insertContent({ type: 'text', text: label, marks: [{ type: 'link', attrs }] });
        } else {
            chain.extendMarkRange('link').setMark('link', attrs);
        }
        chain.run();
        buttonPopover.value?.hide();
    }

    function removeButton(): void {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        buttonPopover.value?.hide();
    }

    function setAlign(align: 'left' | 'center' | 'right' | 'justify'): void {
        if (!editor.value) return;
        editor.value.chain().focus().setTextAlign(align).run();
    }

    function setImageWidth(width: string | null): void {
        if (!editor.value) return;
        editor.value.chain().focus().updateAttributes('image', { width }).run();
    }

    const customWidthInput = ref<string>('');

    function applyCustomWidth(): void {
        const raw = customWidthInput.value.trim();
        if (raw === '') return;

        const match = raw.match(/^(\d{1,4})(?:\s*(%|px))?$/);
        if (!match) return;

        const unit = match[2] ?? '%';
        const value = Number(match[1]);
        if (unit === '%' && (value <= 0 || value > 100)) return;

        setImageWidth(`${value}${unit}`);
        customWidthInput.value = '';
    }

    function setImageAlign(align: 'left' | 'center' | 'right' | null): void {
        if (!editor.value) return;
        editor.value.chain().focus().updateAttributes('image', { 'data-align': align }).run();
    }

    function deleteImage(): void {
        if (!editor.value) return;
        editor.value.chain().focus().deleteSelection().run();
    }

    function insertTable(): void {
        if (!editor.value) return;
        editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
    }

    function deleteTable(): void {
        if (!editor.value) return;
        editor.value.chain().focus().deleteTable().run();
    }

    function addColumnAfter(): void {
        editor.value?.chain().focus().addColumnAfter().run();
    }

    function addRowAfter(): void {
        editor.value?.chain().focus().addRowAfter().run();
    }

    function deleteColumn(): void {
        editor.value?.chain().focus().deleteColumn().run();
    }

    function deleteRow(): void {
        editor.value?.chain().focus().deleteRow().run();
    }

    function setTableBorderStyle(style: TableBorderStyle): void {
        editor.value
            ?.chain()
            .focus()
            .updateAttributes('table', { class: tableClassFor(style) })
            .run();
    }

    function toggleHeaderRow(): void {
        editor.value?.chain().focus().toggleHeaderRow().run();
    }

    const currentTableBorderStyle = computed<TableBorderStyle>(() => {
        if (!editor.value) return 'light';
        return parseTableBorderStyle(editor.value.getAttributes('table').class as string | undefined);
    });

    const colorPopover = ref<InstanceType<typeof Popover> | null>(null);

    function toggleColorPopover(event: Event): void {
        colorPopover.value?.toggle(event);
    }

    function pickColor(hex: string): void {
        editor.value?.chain().focus().setColor(hex).run();
        colorPopover.value?.hide();
    }

    function clearColor(): void {
        editor.value?.chain().focus().unsetColor().run();
        colorPopover.value?.hide();
    }

    const currentColor = computed<string | null>(() => {
        if (!editor.value) return null;
        const color = editor.value.getAttributes('textStyle').color as string | undefined;
        return color ?? null;
    });

    const tableBorderPopover = ref<InstanceType<typeof Popover> | null>(null);

    function toggleTableBorderPopover(event: Event): void {
        tableBorderPopover.value?.toggle(event);
    }

    function pickTableBorderColor(hex: string): void {
        editor.value?.chain().focus().updateAttributes('table', { borderColor: hex }).run();
        tableBorderPopover.value?.hide();
    }

    function clearTableBorderColor(): void {
        editor.value?.chain().focus().updateAttributes('table', { borderColor: null }).run();
        tableBorderPopover.value?.hide();
    }

    const currentTableBorderColor = computed<string | null>(() => {
        if (!editor.value) return null;
        return (editor.value.getAttributes('table').borderColor as string | null) ?? null;
    });

    const highlightPopover = ref<InstanceType<typeof Popover> | null>(null);

    function toggleHighlightPopover(event: Event): void {
        highlightPopover.value?.toggle(event);
    }

    function pickHighlight(hex: string): void {
        editor.value?.chain().focus().setBackgroundColor(hex).run();
        highlightPopover.value?.hide();
    }

    function clearHighlight(): void {
        editor.value?.chain().focus().unsetBackgroundColor().run();
        highlightPopover.value?.hide();
    }

    const currentHighlight = computed<string | null>(() => {
        if (!editor.value) return null;
        return (editor.value.getAttributes('textStyle').backgroundColor as string | undefined) ?? null;
    });

    function clearFormatting(): void {
        editor.value?.chain().focus().unsetAllMarks().clearNodes().run();
    }

    function promptYoutube(): void {
        if (!editor.value) return;
        const url = window.prompt(trans('sk-editor.youtube_prompt'));
        if (!url) return;
        if (!editor.value.chain().focus().setYoutubeVideo({ src: url.trim() }).run()) {
            toast.add({
                severity: 'warn',
                group: 'bc',
                summary: trans('sk-editor.youtube_invalid'),
                life: 3000,
            });
        }
    }

    // Typography — values are what HtmlSanitizer::filterStyle() accepts.
    const fontSizeOptions = ['12px', '14px', '16px', '18px', '20px', '24px', '30px', '36px'].map((v) => ({
        label: v,
        value: v,
    }));
    const fontFamilyOptions = [
        { label: 'Sans', value: 'ui-sans-serif, system-ui, sans-serif' },
        { label: 'Serif', value: 'Georgia, serif' },
        { label: 'Mono', value: 'ui-monospace, monospace' },
    ];
    const lineHeightOptions = ['1', '1.25', '1.5', '1.75', '2'].map((v) => ({
        label: v,
        value: v,
    }));

    function textStyleModel(attr: 'fontSize' | 'fontFamily' | 'lineHeight') {
        return computed<string | null>({
            get: () => (editor.value?.getAttributes('textStyle')[attr] as string | undefined) ?? null,
            set: (value) => {
                const chain = editor.value?.chain().focus();
                if (!chain) return;
                if (attr === 'fontSize') (value ? chain.setFontSize(value) : chain.unsetFontSize()).run();
                if (attr === 'fontFamily') (value ? chain.setFontFamily(value) : chain.unsetFontFamily()).run();
                if (attr === 'lineHeight') (value ? chain.setLineHeight(value) : chain.unsetLineHeight()).run();
            },
        });
    }

    const fontSize = textStyleModel('fontSize');
    const fontFamily = textStyleModel('fontFamily');
    const lineHeight = textStyleModel('lineHeight');

    // HTML source view. The textarea is the raw text the user typed; every
    // keystroke is parsed back through the editor schema and emitted, so a
    // submit while the source view is open still carries the edit. Tags the
    // schema does not know are dropped on that round-trip, so they are listed
    // under the textarea while the user is still typing — never silently.
    const sourceMode = ref(false);
    const sourceHtml = ref('');
    const droppedTags = ref<string[]>([]);
    let resyncingSource = false;

    // Tags the schema rewrites to an equivalent one are not a loss.
    const tagAliases: Record<string, string> = { b: 'strong', i: 'em', del: 's', strike: 's' };

    function tagsIn(html: string): Set<string> {
        const body = new window.DOMParser().parseFromString(html, 'text/html').body;
        return new Set(
            Array.from(body.querySelectorAll('*'), (el) => {
                const tag = el.tagName.toLowerCase();
                return tagAliases[tag] ?? tag;
            }),
        );
    }

    function formatHtml(html: string): string {
        return html.replace(/(<\/(?:p|h[2-4]|ul|ol|li|blockquote|pre|table|thead|tbody|tr|div)>|<hr>)/g, '$1\n').trim();
    }

    function toggleSourceMode(): void {
        if (!editor.value) return;
        if (!sourceMode.value) {
            sourceHtml.value = formatHtml(editor.value.getHTML());
        }
        sourceMode.value = !sourceMode.value;
        if (!sourceMode.value) {
            droppedTags.value = [];
            editor.value.commands.focus();
        }
    }

    // flush: 'sync' — the model is updated inside the input event itself, so
    // no submit can land between a keystroke and the emit.
    watch(
        sourceHtml,
        (html) => {
            if (!sourceMode.value || !editor.value || resyncingSource) return;
            editor.value.commands.setContent(html, { emitUpdate: false });
            syncModelFromEditor();
            const kept = tagsIn(editor.value.getHTML());
            droppedTags.value = [...tagsIn(html)].filter((tag) => !kept.has(tag));
        },
        { flush: 'sync' },
    );

    const fullscreen = ref(false);

    const showLists = computed(() => props.toolbar === 'standard' || props.toolbar === 'full');
    const showFull = computed(() => props.toolbar === 'full');

    const currentImageWidth = computed(() => {
        if (!editor.value) return null;
        return (editor.value.getAttributes('image').width as string | null) ?? null;
    });
    const currentImageAlign = computed(() => {
        if (!editor.value) return null;
        return (editor.value.getAttributes('image')['data-align'] as string | null) ?? null;
    });
</script>

<template>
    <div
        class="sk-rte"
        :class="{
            'sk-rte--invalid': invalid,
            'sk-rte--disabled': disabled,
            'sk-rte--fullscreen': fullscreen,
        }"
        @keydown.esc="fullscreen = false"
    >
        <div v-if="editor" class="sk-rte__toolbar">
            <template v-if="!sourceMode">
                <ButtonGroup>
                    <Button
                        v-tooltip.top="$t('sk-editor.bold')"
                        type="button"
                        label="B"
                        size="small"
                        text
                        :pt="{ label: { class: 'font-bold' } }"
                        :aria-label="$t('sk-editor.bold')"
                        :severity="editor.isActive('bold') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleBold().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.italic')"
                        type="button"
                        label="I"
                        size="small"
                        text
                        :pt="{ label: { class: 'italic font-serif' } }"
                        :aria-label="$t('sk-editor.italic')"
                        :severity="editor.isActive('italic') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleItalic().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.underline')"
                        type="button"
                        label="U"
                        size="small"
                        text
                        :pt="{ label: { class: 'underline' } }"
                        :aria-label="$t('sk-editor.underline')"
                        :severity="editor.isActive('underline') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleUnderline().run()"
                    />
                    <Button
                        v-if="toolbar !== 'minimal'"
                        v-tooltip.top="$t('sk-editor.strike')"
                        type="button"
                        label="S"
                        size="small"
                        text
                        :pt="{ label: { class: 'line-through' } }"
                        :aria-label="$t('sk-editor.strike')"
                        :severity="editor.isActive('strike') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleStrike().run()"
                    />
                </ButtonGroup>

                <ButtonGroup v-if="showFull">
                    <Button
                        v-tooltip.top="$t('sk-editor.inline_code')"
                        type="button"
                        label="<>"
                        size="small"
                        text
                        :pt="{ label: { class: 'font-mono' } }"
                        :aria-label="$t('sk-editor.inline_code')"
                        :severity="editor.isActive('code') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleCode().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.subscript')"
                        type="button"
                        label="x₂"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.subscript')"
                        :severity="editor.isActive('subscript') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleSubscript().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.superscript')"
                        type="button"
                        label="x²"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.superscript')"
                        :severity="editor.isActive('superscript') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleSuperscript().run()"
                    />
                </ButtonGroup>

                <template v-if="showFull">
                    <Select
                        v-model="fontFamily"
                        :options="fontFamilyOptions"
                        option-label="label"
                        option-value="value"
                        size="small"
                        show-clear
                        class="sk-rte__select"
                        :placeholder="$t('sk-editor.font_family')"
                        :aria-label="$t('sk-editor.font_family')"
                        :disabled="disabled"
                    />
                    <Select
                        v-model="fontSize"
                        :options="fontSizeOptions"
                        option-label="label"
                        option-value="value"
                        size="small"
                        show-clear
                        class="sk-rte__select"
                        :placeholder="$t('sk-editor.font_size')"
                        :aria-label="$t('sk-editor.font_size')"
                        :disabled="disabled"
                    />
                    <Select
                        v-model="lineHeight"
                        :options="lineHeightOptions"
                        option-label="label"
                        option-value="value"
                        size="small"
                        show-clear
                        class="sk-rte__select"
                        :placeholder="$t('sk-editor.line_height')"
                        :aria-label="$t('sk-editor.line_height')"
                        :disabled="disabled"
                    />
                </template>

                <ButtonGroup v-if="showFull">
                    <Button
                        v-tooltip.top="$t('sk-editor.h2')"
                        type="button"
                        label="H2"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.h2')"
                        :severity="editor.isActive('heading', { level: 2 }) ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.h3')"
                        type="button"
                        label="H3"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.h3')"
                        :severity="editor.isActive('heading', { level: 3 }) ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.h4')"
                        type="button"
                        label="H4"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.h4')"
                        :severity="editor.isActive('heading', { level: 4 }) ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleHeading({ level: 4 }).run()"
                    />
                </ButtonGroup>

                <ButtonGroup v-if="showLists">
                    <Button
                        v-tooltip.top="$t('sk-editor.bullet_list')"
                        type="button"
                        icon="pi pi-list"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.bullet_list')"
                        :severity="editor.isActive('bulletList') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleBulletList().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.ordered_list')"
                        type="button"
                        icon="pi pi-sort-numeric-down"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.ordered_list')"
                        :severity="editor.isActive('orderedList') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleOrderedList().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.task_list')"
                        type="button"
                        icon="pi pi-check-square"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.task_list')"
                        :severity="editor.isActive('taskList') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleTaskList().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.blockquote')"
                        type="button"
                        icon="pi pi-align-justify"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.blockquote')"
                        :severity="editor.isActive('blockquote') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleBlockquote().run()"
                    />
                </ButtonGroup>

                <ButtonGroup v-if="showLists">
                    <Button
                        v-tooltip.top="$t('sk-editor.align_left')"
                        type="button"
                        icon="pi pi-align-left"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.align_left')"
                        :severity="editor.isActive({ textAlign: 'left' }) ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="setAlign('left')"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.align_center')"
                        type="button"
                        icon="pi pi-align-center"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.align_center')"
                        :severity="editor.isActive({ textAlign: 'center' }) ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="setAlign('center')"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.align_right')"
                        type="button"
                        icon="pi pi-align-right"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.align_right')"
                        :severity="editor.isActive({ textAlign: 'right' }) ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="setAlign('right')"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.align_justify')"
                        type="button"
                        icon="pi pi-align-justify"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.align_justify')"
                        :severity="editor.isActive({ textAlign: 'justify' }) ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="setAlign('justify')"
                    />
                </ButtonGroup>

                <ButtonGroup>
                    <Button
                        v-tooltip.top="$t('sk-editor.color')"
                        type="button"
                        icon="pi pi-palette"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.color')"
                        :pt="{
                            icon: { style: currentColor ? { color: currentColor } : {} },
                        }"
                        :severity="currentColor ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="toggleColorPopover"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.highlight')"
                        type="button"
                        icon="pi pi-pencil"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.highlight')"
                        :pt="{
                            icon: {
                                style: currentHighlight ? { backgroundColor: currentHighlight } : {},
                            },
                        }"
                        :severity="currentHighlight ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="toggleHighlightPopover"
                    />
                    <Button
                        v-if="toolbar !== 'minimal'"
                        v-tooltip.top="$t('sk-editor.clear_format')"
                        type="button"
                        icon="pi pi-eraser"
                        size="small"
                        text
                        severity="secondary"
                        :aria-label="$t('sk-editor.clear_format')"
                        :disabled="disabled"
                        @click="clearFormatting"
                    />
                </ButtonGroup>

                <ButtonGroup v-if="links || imageUpload">
                    <Button
                        v-if="links"
                        v-tooltip.top="$t('sk-editor.link')"
                        type="button"
                        icon="pi pi-link"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.link')"
                        :severity="editor.isActive('link') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="promptLink"
                    />
                    <Button
                        v-if="links"
                        v-tooltip.top="$t('sk-editor.button')"
                        type="button"
                        icon="pi pi-stop"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.button')"
                        :severity="currentButton ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="toggleButtonPopover"
                    />
                    <Button
                        v-if="imageUpload"
                        v-tooltip.top="$t('sk-editor.image')"
                        type="button"
                        icon="pi pi-image"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.image')"
                        :loading="uploadingImage"
                        :disabled="disabled || uploadingImage"
                        @click="pickImage"
                    />
                </ButtonGroup>

                <ButtonGroup v-if="showLists">
                    <Button
                        v-tooltip.top="$t('sk-editor.table_insert')"
                        type="button"
                        icon="pi pi-table"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.table_insert')"
                        :severity="editor.isActive('table') ? 'primary' : 'secondary'"
                        :disabled="disabled || editor.isActive('table')"
                        @click="insertTable"
                    />
                </ButtonGroup>

                <ButtonGroup v-if="showFull">
                    <Button
                        v-tooltip.top="$t('sk-editor.code_block')"
                        type="button"
                        icon="pi pi-code"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.code_block')"
                        :severity="editor.isActive('codeBlock') ? 'primary' : 'secondary'"
                        :disabled="disabled"
                        @click="editor.chain().focus().toggleCodeBlock().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.horizontal_rule')"
                        type="button"
                        icon="pi pi-minus"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.horizontal_rule')"
                        :disabled="disabled"
                        @click="editor.chain().focus().setHorizontalRule().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.youtube')"
                        type="button"
                        icon="pi pi-youtube"
                        size="small"
                        text
                        severity="secondary"
                        :aria-label="$t('sk-editor.youtube')"
                        :disabled="disabled"
                        @click="promptYoutube"
                    />
                </ButtonGroup>

                <ButtonGroup v-if="showFull">
                    <Button
                        v-tooltip.top="$t('sk-editor.undo')"
                        type="button"
                        icon="pi pi-undo"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.undo')"
                        :disabled="disabled || !editor.can().undo()"
                        @click="editor.chain().focus().undo().run()"
                    />
                    <Button
                        v-tooltip.top="$t('sk-editor.redo')"
                        type="button"
                        icon="pi pi-refresh"
                        size="small"
                        text
                        :aria-label="$t('sk-editor.redo')"
                        :disabled="disabled || !editor.can().redo()"
                        @click="editor.chain().focus().redo().run()"
                    />
                </ButtonGroup>
            </template>

            <ButtonGroup class="ml-auto">
                <Button
                    v-tooltip.top="$t('sk-editor.source')"
                    type="button"
                    icon="pi pi-code"
                    size="small"
                    text
                    :aria-label="$t('sk-editor.source')"
                    :aria-pressed="sourceMode"
                    :severity="sourceMode ? 'primary' : 'secondary'"
                    :disabled="disabled"
                    @click="toggleSourceMode"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.fullscreen')"
                    type="button"
                    :icon="fullscreen ? 'pi pi-window-minimize' : 'pi pi-window-maximize'"
                    size="small"
                    text
                    :aria-label="$t('sk-editor.fullscreen')"
                    :aria-pressed="fullscreen"
                    :severity="fullscreen ? 'primary' : 'secondary'"
                    @click="fullscreen = !fullscreen"
                />
            </ButtonGroup>
        </div>

        <BubbleMenu
            v-if="editor && imageUpload"
            :editor="editor"
            :should-show="({ editor }) => editor.isActive('image')"
            plugin-key="sk-editor-image-bubble"
        >
            <div class="sk-rte__bubble">
                <Button
                    v-tooltip.top="$t('sk-editor.image_size_s')"
                    type="button"
                    label="S"
                    size="small"
                    text
                    :severity="currentImageWidth === '200px' ? 'primary' : 'secondary'"
                    @click="setImageWidth('200px')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.image_size_m')"
                    type="button"
                    label="M"
                    size="small"
                    text
                    :severity="currentImageWidth === '400px' ? 'primary' : 'secondary'"
                    @click="setImageWidth('400px')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.image_size_l')"
                    type="button"
                    label="L"
                    size="small"
                    text
                    :severity="currentImageWidth === '600px' ? 'primary' : 'secondary'"
                    @click="setImageWidth('600px')"
                />
                <Button
                    type="button"
                    label="100%"
                    size="small"
                    text
                    :severity="currentImageWidth === '100%' ? 'primary' : 'secondary'"
                    @click="setImageWidth('100%')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.image_size_auto')"
                    type="button"
                    icon="pi pi-expand"
                    size="small"
                    text
                    :aria-label="$t('sk-editor.image_size_auto')"
                    :severity="currentImageWidth === null ? 'primary' : 'secondary'"
                    @click="setImageWidth(null)"
                />
                <span class="sk-rte__bubble-sep" />
                <InputText
                    v-model="customWidthInput"
                    v-tooltip.top="$t('sk-editor.image_width_placeholder')"
                    size="small"
                    class="sk-rte__bubble-input"
                    :placeholder="currentImageWidth ?? $t('sk-editor.image_width_placeholder')"
                    :aria-label="$t('sk-editor.image_width_placeholder')"
                    @keydown.enter.prevent="applyCustomWidth"
                    @blur="applyCustomWidth"
                />
                <span class="sk-rte__bubble-sep" />
                <Button
                    v-tooltip.top="$t('sk-editor.align_left')"
                    type="button"
                    icon="pi pi-align-left"
                    size="small"
                    text
                    :aria-label="$t('sk-editor.align_left')"
                    :severity="currentImageAlign === 'left' ? 'primary' : 'secondary'"
                    @click="setImageAlign('left')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.align_center')"
                    type="button"
                    icon="pi pi-align-center"
                    size="small"
                    text
                    :aria-label="$t('sk-editor.align_center')"
                    :severity="currentImageAlign === 'center' ? 'primary' : 'secondary'"
                    @click="setImageAlign('center')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.align_right')"
                    type="button"
                    icon="pi pi-align-right"
                    size="small"
                    text
                    :aria-label="$t('sk-editor.align_right')"
                    :severity="currentImageAlign === 'right' ? 'primary' : 'secondary'"
                    @click="setImageAlign('right')"
                />
                <span class="sk-rte__bubble-sep" />
                <Button
                    v-tooltip.top="$t('sk-editor.delete_image')"
                    type="button"
                    icon="pi pi-trash"
                    size="small"
                    text
                    severity="danger"
                    :aria-label="$t('sk-editor.delete_image')"
                    @click="deleteImage"
                />
            </div>
        </BubbleMenu>

        <BubbleMenu
            v-if="editor"
            :editor="editor"
            :should-show="({ editor }) => editor.isActive('table') && !editor.isActive('image')"
            plugin-key="sk-editor-table-bubble"
        >
            <div class="sk-rte__bubble">
                <Button
                    v-tooltip.top="$t('sk-editor.table_add_column')"
                    type="button"
                    size="small"
                    text
                    :label="`+ ${$t('sk-editor.table_col')}`"
                    :disabled="disabled"
                    @click="addColumnAfter"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.table_add_row')"
                    type="button"
                    size="small"
                    text
                    :label="`+ ${$t('sk-editor.table_row')}`"
                    :disabled="disabled"
                    @click="addRowAfter"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.table_delete_column')"
                    type="button"
                    size="small"
                    text
                    severity="warn"
                    :label="`− ${$t('sk-editor.table_col')}`"
                    :disabled="disabled"
                    @click="deleteColumn"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.table_delete_row')"
                    type="button"
                    size="small"
                    text
                    severity="warn"
                    :label="`− ${$t('sk-editor.table_row')}`"
                    :disabled="disabled"
                    @click="deleteRow"
                />
                <span class="sk-rte__bubble-sep" />
                <Button
                    v-tooltip.top="$t('sk-editor.table_toggle_header')"
                    type="button"
                    icon="pi pi-th-large"
                    size="small"
                    text
                    :disabled="disabled"
                    @click="toggleHeaderRow"
                />
                <span class="sk-rte__bubble-sep" />
                <Button
                    v-tooltip.top="$t('sk-editor.table_border_none')"
                    type="button"
                    label="—"
                    size="small"
                    text
                    :severity="currentTableBorderStyle === 'none' ? 'primary' : 'secondary'"
                    @click="setTableBorderStyle('none')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.table_border_light')"
                    type="button"
                    label="░"
                    size="small"
                    text
                    :pt="{ label: { class: 'text-surface-400' } }"
                    :severity="currentTableBorderStyle === 'light' ? 'primary' : 'secondary'"
                    @click="setTableBorderStyle('light')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.table_border_normal')"
                    type="button"
                    label="▦"
                    size="small"
                    text
                    :severity="currentTableBorderStyle === 'normal' ? 'primary' : 'secondary'"
                    @click="setTableBorderStyle('normal')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.table_border_bold')"
                    type="button"
                    label="▣"
                    size="small"
                    text
                    :pt="{ label: { class: 'font-bold' } }"
                    :severity="currentTableBorderStyle === 'bold' ? 'primary' : 'secondary'"
                    @click="setTableBorderStyle('bold')"
                />
                <Button
                    v-tooltip.top="$t('sk-editor.table_border_custom')"
                    type="button"
                    icon="pi pi-palette"
                    size="small"
                    text
                    :pt="{
                        icon: {
                            style: currentTableBorderColor ? { color: currentTableBorderColor } : {},
                        },
                    }"
                    :severity="currentTableBorderColor ? 'primary' : 'secondary'"
                    @click="toggleTableBorderPopover"
                />
                <span class="sk-rte__bubble-sep" />
                <Button
                    v-tooltip.top="$t('sk-editor.table_delete')"
                    type="button"
                    icon="pi pi-trash"
                    size="small"
                    text
                    severity="danger"
                    :disabled="disabled"
                    @click="deleteTable"
                />
            </div>
        </BubbleMenu>

        <Popover ref="colorPopover">
            <EditorColorPalette :current="currentColor" @pick="pickColor" @clear="clearColor" />
        </Popover>

        <Popover ref="tableBorderPopover">
            <EditorColorPalette
                :current="currentTableBorderColor"
                @pick="pickTableBorderColor"
                @clear="clearTableBorderColor"
            />
        </Popover>

        <Popover ref="buttonPopover">
            <form class="sk-rte__button-form" @submit.prevent="applyButton">
                <InputText
                    v-if="buttonNeedsLabel"
                    v-model="buttonForm.label"
                    size="small"
                    :placeholder="$t('sk-editor.button_label')"
                    :aria-label="$t('sk-editor.button_label')"
                />
                <InputText
                    v-model="buttonForm.href"
                    size="small"
                    type="url"
                    :placeholder="$t('sk-editor.button_url')"
                    :aria-label="$t('sk-editor.button_url')"
                />
                <SelectButton
                    v-model="buttonForm.variant"
                    :options="buttonVariantOptions"
                    option-label="label"
                    option-value="value"
                    size="small"
                    :allow-empty="false"
                    :aria-label="$t('sk-editor.button_style')"
                />
                <EditorColorPalette
                    v-if="buttonForm.variant !== 'secondary'"
                    :current="buttonForm.color"
                    @pick="(hex) => (buttonForm.color = hex)"
                    @clear="buttonForm.color = null"
                />
                <div class="sk-rte__button-form-actions">
                    <Button
                        v-if="currentButton"
                        type="button"
                        size="small"
                        text
                        severity="danger"
                        :label="$t('sk-editor.button_remove')"
                        @click="removeButton"
                    />
                    <Button type="submit" size="small" :label="$t('sk-editor.button_apply')" />
                </div>
            </form>
        </Popover>

        <Popover ref="highlightPopover">
            <EditorColorPalette :current="currentHighlight" @pick="pickHighlight" @clear="clearHighlight" />
        </Popover>

        <textarea
            v-if="sourceMode"
            v-model="sourceHtml"
            class="sk-rte__source"
            spellcheck="false"
            :disabled="disabled"
            :style="{ minHeight }"
            :aria-label="$t('sk-editor.source')"
            :aria-describedby="droppedTags.length ? `${id ?? 'sk-rte'}-dropped` : undefined"
        />
        <div
            v-if="sourceMode && droppedTags.length"
            :id="`${id ?? 'sk-rte'}-dropped`"
            class="sk-rte__source-warning"
            role="status"
        >
            <i class="pi pi-exclamation-triangle" aria-hidden="true" />
            {{ $t('sk-editor.source_dropped', { tags: droppedTags.map((t) => `<${t}>`).join(', ') }) }}
        </div>
        <EditorContent v-show="!sourceMode" :editor="editor" class="sk-rte__body" :style="{ minHeight }" />
    </div>
</template>
