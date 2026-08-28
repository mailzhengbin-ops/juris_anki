import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';

export default function Appearance() {
    return (
        <>
            <Head title="外观设置" />

            <h1 className="sr-only">外观设置</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Appearance settings"
                    description="调整界面的明暗外观"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

