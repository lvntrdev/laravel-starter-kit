// resources/js/types/sample-content.ts

export interface TranslatableValue {
    translations: Record<string, string>;
    current: string;
}

export interface SampleContent {
    id: number;
    title: TranslatableValue;
    description: TranslatableValue;
    content: TranslatableValue;
    user: {
        id: string;
        name: string;
    };
    created_at: string;
    updated_at: string;
}
