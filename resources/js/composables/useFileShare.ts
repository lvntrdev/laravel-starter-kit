// resources/js/composables/useFileShare.ts

import { useApi } from '@/composables/useApi';
import { trans } from 'laravel-vue-i18n';
import { useToast } from 'primevue/usetoast';

export interface ShareLinkResult {
    url: string;
    expires_at: string;
    token_hash: string;
}

export function useFileShare() {
    const api = useApi({ toast: false });
    const toast = useToast();

    /**
     * Signed paylaşım linki oluşturur.
     *
     * @param mediaId  Medialibrary media kaydının ID'si.
     * @param ttlHours Link geçerlilik süresi (saat). Min: 1, Max: 720.
     * @returns        ShareLinkResult veya hata durumunda null.
     */
    async function createShare(mediaId: number, ttlHours: number): Promise<ShareLinkResult | null> {
        try {
            const result = await api.post<ShareLinkResult>('/file-manager/share', {
                media_id: mediaId,
                expires_in_hours: ttlHours,
            });
            return result;
        } catch (error: unknown) {
            const message =
                error instanceof Error
                    ? error.message
                    : trans('sk-file-manager.share.errors.create_failed');

            toast.add({
                severity: 'error',
                summary: trans('sk-file-manager.share.errors.summary'),
                detail: message,
                group: 'bc',
                life: 5000,
            });
            return null;
        }
    }

    /**
     * Paylaşım linkini iptal eder (revoke).
     *
     * @param mediaId Medialibrary media kaydının ID'si.
     * @param token   Revoke edilecek token hash (SHA-256 hex).
     * @returns       Başarılıysa true, aksi halde false.
     */
    async function revokeShare(mediaId: number, token: string): Promise<boolean> {
        try {
            await api.post('/file-manager/share/revoke', {
                media_id: mediaId,
                token,
            });
            toast.add({
                severity: 'success',
                summary: '',
                detail: trans('sk-file-manager.share.revoked'),
                group: 'bc',
                life: 2500,
            });
            return true;
        } catch (error: unknown) {
            const message =
                error instanceof Error
                    ? error.message
                    : trans('sk-file-manager.share.errors.revoke_failed');

            toast.add({
                severity: 'error',
                summary: trans('sk-file-manager.share.errors.summary'),
                detail: message,
                group: 'bc',
                life: 5000,
            });
            return false;
        }
    }

    return {
        createShare,
        revokeShare,
    };
}
