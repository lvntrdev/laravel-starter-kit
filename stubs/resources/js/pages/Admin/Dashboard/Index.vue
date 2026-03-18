<script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import { Head, usePage } from '@inertiajs/vue3';

    const page = usePage();
    const user = computed(() => page.props.auth?.user);
    const stats = [
        { label: 'Total Users', value: '1,234', icon: 'pi pi-users', color: 'text-blue-500' },
        { label: 'Active Sessions', value: '56', icon: 'pi pi-bolt', color: 'text-green-500' },
        { label: "Today's Requests", value: '8,901', icon: 'pi pi-chart-bar', color: 'text-orange-500' },
        {
            label: 'System Status',
            value: 'Active',
            icon: 'pi pi-check-circle',
            color: 'text-emerald-500',
        },
    ];
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <div class="w-full">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                    Dashboard
                </h1>
                <p class="mt-1 text-surface-500 dark:text-surface-400">
                    Welcome, {{ user?.name }}
                </p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card v-for="stat in stats" :key="stat.label">
                    <template #content>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-surface-500 dark:text-surface-400">
                                    {{ stat.label }}
                                </p>
                                <p class="text-2xl font-bold text-surface-900 dark:text-surface-0 mt-1">
                                    {{ stat.value }}
                                </p>
                            </div>
                            <i :class="[stat.icon, stat.color]" class="text-3xl" />
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Account Info -->
            <Card>
                <template #title>
                    Account Information
                </template>
                <template #content>
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm font-medium text-surface-500 dark:text-surface-400">Full Name</span>
                            <p class="text-surface-900 dark:text-surface-0">
                                {{ user?.name }}
                            </p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-surface-500 dark:text-surface-400">Email</span>
                            <p class="text-surface-900 dark:text-surface-0">
                                {{ user?.email }}
                            </p>
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <div>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque luctus urna id dui maximus iaculis. Vivamus
            lacinia sodales consectetur. Praesent finibus, felis ac lobortis egestas, purus ligula hendrerit odio, id
            fringilla ligula velit ut magna. Quisque eget nisi in augue interdum faucibus. In consectetur suscipit
            tincidunt. Quisque sed est velit. Proin tempor purus vel justo sollicitudin, eu vestibulum lacus viverra.
            Phasellus malesuada fermentum ultrices. Fusce eget dui pharetra, varius mi ac, tincidunt risus. Pellentesque
            id posuere tellus. Nulla sed quam vitae dui pellentesque finibus. Donec non nisl felis. Nullam aliquet
            congue tempus. Cras hendrerit neque quis enim malesuada viverra. Fusce vel dolor id nisi convallis
            elementum. Aliquam ut est magna. Etiam egestas molestie pretium. Nunc tristique odio eu eros vulputate, quis
            consequat tortor viverra. Donec ultricies luctus massa, in mattis orci molestie in. Morbi sagittis
            consectetur diam. In sed metus ut diam cursus placerat nec sed urna. Donec eu vehicula lectus. Nullam
            consectetur posuere turpis, nec porttitor arcu sollicitudin eu. Nunc placerat velit quam, nec auctor odio
            cursus viverra. Donec quis aliquam ipsum. Etiam quis blandit sapien. Maecenas fermentum laoreet volutpat. In
            ut semper erat. Aenean semper leo lorem, non molestie risus congue eu. In varius ligula eu pretium tempor.
            Morbi in quam efficitur, commodo ligula sed, pulvinar ex. Donec semper, ex vel sagittis pulvinar, ex neque
            ornare leo, eget euismod lacus lorem ac tellus. Nullam congue feugiat sem nec lobortis. Phasellus
            consectetur sollicitudin bibendum. Suspendisse tortor nisi, pretium eget tincidunt quis, pellentesque ac
            magna. Proin ac nisi quis elit porta imperdiet sed eget nisl. Cras feugiat auctor risus, vel scelerisque mi
            pulvinar quis. Nulla molestie vestibulum velit. Vestibulum porttitor ipsum purus, non aliquet felis porta
            vel. Suspendisse imperdiet posuere odio, in vehicula elit feugiat vitae. Pellentesque vulputate fermentum
            egestas. In finibus lorem rutrum ornare placerat. Sed id nunc quis erat venenatis dapibus. Quisque sed diam
            in nibh finibus rhoncus vitae id nisi. Morbi luctus nulla vitae lobortis efficitur. Etiam mollis nisl a
            dolor venenatis, vitae luctus erat pulvinar. Suspendisse enim nisl, lobortis id tortor ut, egestas placerat
            ipsum. Phasellus eros justo, sodales et dui quis, accumsan tincidunt justo. Vestibulum ante ipsum primis in
            faucibus orci luctus et ultrices posuere cubilia curae; Phasellus semper enim nec consectetur facilisis. Sed
            facilisis, ipsum vel ornare consequat, eros lacus vestibulum erat, quis mollis metus orci et lorem. Donec
            augue nulla, auctor efficitur turpis quis, hendrerit interdum metus. In ultricies, elit vel tincidunt
            iaculis, est enim aliquet augue, quis egestas metus libero at dui. Sed venenatis ipsum massa, in pulvinar
            arcu pretium nec. Nullam elementum malesuada tristique. Proin vel est quis dui consequat pretium. Etiam ac
            magna libero. In a odio ante. Vestibulum ac nibh mollis, viverra massa sit amet, cursus tortor. Duis porta
            risus ut luctus ultricies. Praesent convallis turpis nisl, vitae commodo purus varius et. Maecenas ac nunc
            dapibus, iaculis velit at, maximus mauris. Ut nibh ipsum, euismod at dui molestie, mollis sollicitudin
            dolor. Duis eleifend sollicitudin pretium. Duis semper ante condimentum ipsum hendrerit, id sollicitudin
            sapien pellentesque. Suspendisse nec pulvinar ante. Suspendisse augue ante, euismod non ornare in, fermentum
            non ipsum. Nulla suscipit, augue pellentesque pharetra volutpat, erat ipsum posuere elit, et ullamcorper
            eros nisi in elit. Suspendisse massa erat, gravida at dui ut, porttitor aliquam eros. Donec risus ex,
            iaculis vitae mi ac, molestie vulputate turpis. Aenean congue dictum ipsum nec efficitur. Nam suscipit odio
            et mattis rhoncus. Ut nulla lectus, aliquet eu pretium eu, suscipit scelerisque mi. Donec blandit arcu at
            arcu pretium, id congue neque lobortis. Morbi efficitur lorem at congue gravida. Integer semper blandit
            odio, a consectetur nisi rhoncus dapibus. Nam eu elementum magna. Curabitur eget convallis nibh. Sed
            interdum eros non feugiat tristique. Suspendisse volutpat mi quis purus blandit rhoncus. Sed nec lobortis
            felis. Quisque scelerisque massa quis purus accumsan vestibulum non tempus turpis. Nam fringilla facilisis
            tortor id ultrices. Donec hendrerit efficitur leo, convallis tempor lorem maximus at. Vivamus gravida nunc
            ex, eu pulvinar arcu tincidunt consectetur. Quisque cursus elit vitae massa molestie cursus eget hendrerit
            massa. Sed accumsan nibh nisi, vitae condimentum tortor tempus non. Aenean pulvinar orci augue, sit amet
            porta urna porttitor id. Sed mollis interdum dui sit amet viverra. Vestibulum sed vulputate odio, vel
            fermentum ligula. Morbi laoreet a nisl ac sollicitudin. Orci varius natoque penatibus et magnis dis
            parturient montes, nascetur ridiculus mus. Nam cursus pulvinar efficitur. Sed eget ante tortor. Integer vel
            dui vel est rutrum gravida. Curabitur elementum placerat tempor. Quisque rutrum finibus iaculis.
            Pellentesque vulputate egestas ligula, at interdum nibh lobortis vel. Proin lectus ipsum, gravida id
            vehicula a, pretium quis urna. Maecenas eget sem tincidunt, pharetra urna feugiat, finibus mi. Maecenas
            efficitur, magna at lobortis ullamcorper, dui libero venenatis urna, nec luctus velit justo id sem. Nam
            convallis feugiat eros in interdum. Proin in odio ultrices, eleifend libero a, lacinia nisl. Aliquam erat
            volutpat. Mauris lacinia nulla massa, sed scelerisque ipsum scelerisque ut. Cras mollis erat id interdum
            porttitor. Nulla a sodales elit. Integer viverra elit at nulla cursus, ac fringilla neque ornare. Cras
            posuere et mi non rutrum. Cras placerat felis non placerat tempor. Mauris eget lectus dictum, euismod arcu
            et, elementum massa.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque luctus urna id dui
            maximus iaculis. Vivamus lacinia sodales consectetur. Praesent finibus, felis ac lobortis egestas, purus
            ligula hendrerit odio, id fringilla ligula velit ut magna. Quisque eget nisi in augue interdum faucibus. In
            consectetur suscipit tincidunt. Quisque sed est velit. Proin tempor purus vel justo sollicitudin, eu
            vestibulum lacus viverra. Phasellus malesuada fermentum ultrices. Fusce eget dui pharetra, varius mi ac,
            tincidunt risus. Pellentesque id posuere tellus. Nulla sed quam vitae dui pellentesque finibus. Donec non
            nisl felis. Nullam aliquet congue tempus. Cras hendrerit neque quis enim malesuada viverra. Fusce vel dolor
            id nisi convallis elementum. Aliquam ut est magna. Etiam egestas molestie pretium. Nunc tristique odio eu
            eros vulputate, quis consequat tortor viverra. Donec ultricies luctus massa, in mattis orci molestie in.
            Morbi sagittis consectetur diam. In sed metus ut diam cursus placerat nec sed urna. Donec eu vehicula
            lectus. Nullam consectetur posuere turpis, nec porttitor arcu sollicitudin eu. Nunc placerat velit quam, nec
            auctor odio cursus viverra. Donec quis aliquam ipsum. Etiam quis blandit sapien. Maecenas fermentum laoreet
            volutpat. In ut semper erat. Aenean semper leo lorem, non molestie risus congue eu. In varius ligula eu
            pretium tempor. Morbi in quam efficitur, commodo ligula sed, pulvinar ex. Donec semper, ex vel sagittis
            pulvinar, ex neque ornare leo, eget euismod lacus lorem ac tellus. Nullam congue feugiat sem nec lobortis.
            Phasellus consectetur sollicitudin bibendum. Suspendisse tortor nisi, pretium eget tincidunt quis,
            pellentesque ac magna. Proin ac nisi quis elit porta imperdiet sed eget nisl. Cras feugiat auctor risus, vel
            scelerisque mi pulvinar quis. Nulla molestie vestibulum velit. Vestibulum porttitor ipsum purus, non aliquet
            felis porta vel. Suspendisse imperdiet posuere odio, in vehicula elit feugiat vitae. Pellentesque vulputate
            fermentum egestas. In finibus lorem rutrum ornare placerat. Sed id nunc quis erat venenatis dapibus. Quisque
            sed diam in nibh finibus rhoncus vitae id nisi. Morbi luctus nulla vitae lobortis efficitur. Etiam mollis
            nisl a dolor venenatis, vitae luctus erat pulvinar. Suspendisse enim nisl, lobortis id tortor ut, egestas
            placerat ipsum. Phasellus eros justo, sodales et dui quis, accumsan tincidunt justo. Vestibulum ante ipsum
            primis in faucibus orci luctus et ultrices posuere cubilia curae; Phasellus semper enim nec consectetur
            facilisis. Sed facilisis, ipsum vel ornare consequat, eros lacus vestibulum erat, quis mollis metus orci et
            lorem. Donec augue nulla, auctor efficitur turpis quis, hendrerit interdum metus. In ultricies, elit vel
            tincidunt iaculis, est enim aliquet augue, quis egestas metus libero at dui. Sed venenatis ipsum massa, in
            pulvinar arcu pretium nec. Nullam elementum malesuada tristique. Proin vel est quis dui consequat pretium.
            Etiam ac magna libero. In a odio ante. Vestibulum ac nibh mollis, viverra massa sit amet, cursus tortor.
            Duis porta risus ut luctus ultricies. Praesent convallis turpis nisl, vitae commodo purus varius et.
            Maecenas ac nunc dapibus, iaculis velit at, maximus mauris. Ut nibh ipsum, euismod at dui molestie, mollis
            sollicitudin dolor. Duis eleifend sollicitudin pretium. Duis semper ante condimentum ipsum hendrerit, id
            sollicitudin sapien pellentesque. Suspendisse nec pulvinar ante. Suspendisse augue ante, euismod non ornare
            in, fermentum non ipsum. Nulla suscipit, augue pellentesque pharetra volutpat, erat ipsum posuere elit, et
            ullamcorper eros nisi in elit. Suspendisse massa erat, gravida at dui ut, porttitor aliquam eros. Donec
            risus ex, iaculis vitae mi ac, molestie vulputate turpis. Aenean congue dictum ipsum nec efficitur. Nam
            suscipit odio et mattis rhoncus. Ut nulla lectus, aliquet eu pretium eu, suscipit scelerisque mi. Donec
            blandit arcu at arcu pretium, id congue neque lobortis. Morbi efficitur lorem at congue gravida. Integer
            semper blandit odio, a consectetur nisi rhoncus dapibus. Nam eu elementum magna. Curabitur eget convallis
            nibh. Sed interdum eros non feugiat tristique. Suspendisse volutpat mi quis purus blandit rhoncus. Sed nec
            lobortis felis. Quisque scelerisque massa quis purus accumsan vestibulum non tempus turpis. Nam fringilla
            facilisis tortor id ultrices. Donec hendrerit efficitur leo, convallis tempor lorem maximus at. Vivamus
            gravida nunc ex, eu pulvinar arcu tincidunt consectetur. Quisque cursus elit vitae massa molestie cursus
            eget hendrerit massa. Sed accumsan nibh nisi, vitae condimentum tortor tempus non. Aenean pulvinar orci
            augue, sit amet porta urna porttitor id. Sed mollis interdum dui sit amet viverra. Vestibulum sed vulputate
            odio, vel fermentum ligula. Morbi laoreet a nisl ac sollicitudin. Orci varius natoque penatibus et magnis
            dis parturient montes, nascetur ridiculus mus. Nam cursus pulvinar efficitur. Sed eget ante tortor. Integer
            vel dui vel est rutrum gravida. Curabitur elementum placerat tempor. Quisque rutrum finibus iaculis.
            Pellentesque vulputate egestas ligula, at interdum nibh lobortis vel. Proin lectus ipsum, gravida id
            vehicula a, pretium quis urna. Maecenas eget sem tincidunt, pharetra urna feugiat, finibus mi. Maecenas
            efficitur, magna at lobortis ullamcorper, dui libero venenatis urna, nec luctus velit justo id sem. Nam
            convallis feugiat eros in interdum. Proin in odio ultrices, eleifend libero a, lacinia nisl. Aliquam erat
            volutpat. Mauris lacinia nulla massa, sed scelerisque ipsum scelerisque ut. Cras mollis erat id interdum
            porttitor. Nulla a sodales elit. Integer viverra elit at nulla cursus, ac fringilla neque ornare. Cras
            posuere et mi non rutrum. Cras placerat felis non placerat tempor. Mauris eget lectus dictum, euismod arcu
            et, elementum massa.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque luctus urna id dui
            maximus iaculis. Vivamus lacinia sodales consectetur. Praesent finibus, felis ac lobortis egestas, purus
            ligula hendrerit odio, id fringilla ligula velit ut magna. Quisque eget nisi in augue interdum faucibus. In
            consectetur suscipit tincidunt. Quisque sed est velit. Proin tempor purus vel justo sollicitudin, eu
            vestibulum lacus viverra. Phasellus malesuada fermentum ultrices. Fusce eget dui pharetra, varius mi ac,
            tincidunt risus. Pellentesque id posuere tellus. Nulla sed quam vitae dui pellentesque finibus. Donec non
            nisl felis. Nullam aliquet congue tempus. Cras hendrerit neque quis enim malesuada viverra. Fusce vel dolor
            id nisi convallis elementum. Aliquam ut est magna. Etiam egestas molestie pretium. Nunc tristique odio eu
            eros vulputate, quis consequat tortor viverra. Donec ultricies luctus massa, in mattis orci molestie in.
            Morbi sagittis consectetur diam. In sed metus ut diam cursus placerat nec sed urna. Donec eu vehicula
            lectus. Nullam consectetur posuere turpis, nec porttitor arcu sollicitudin eu. Nunc placerat velit quam, nec
            auctor odio cursus viverra. Donec quis aliquam ipsum. Etiam quis blandit sapien. Maecenas fermentum laoreet
            volutpat. In ut semper erat. Aenean semper leo lorem, non molestie risus congue eu. In varius ligula eu
            pretium tempor. Morbi in quam efficitur, commodo ligula sed, pulvinar ex. Donec semper, ex vel sagittis
            pulvinar, ex neque ornare leo, eget euismod lacus lorem ac tellus. Nullam congue feugiat sem nec lobortis.
            Phasellus consectetur sollicitudin bibendum. Suspendisse tortor nisi, pretium eget tincidunt quis,
            pellentesque ac magna. Proin ac nisi quis elit porta imperdiet sed eget nisl. Cras feugiat auctor risus, vel
            scelerisque mi pulvinar quis. Nulla molestie vestibulum velit. Vestibulum porttitor ipsum purus, non aliquet
            felis porta vel. Suspendisse imperdiet posuere odio, in vehicula elit feugiat vitae. Pellentesque vulputate
            fermentum egestas. In finibus lorem rutrum ornare placerat. Sed id nunc quis erat venenatis dapibus. Quisque
            sed diam in nibh finibus rhoncus vitae id nisi. Morbi luctus nulla vitae lobortis efficitur. Etiam mollis
            nisl a dolor venenatis, vitae luctus erat pulvinar. Suspendisse enim nisl, lobortis id tortor ut, egestas
            placerat ipsum. Phasellus eros justo, sodales et dui quis, accumsan tincidunt justo. Vestibulum ante ipsum
            primis in faucibus orci luctus et ultrices posuere cubilia curae; Phasellus semper enim nec consectetur
            facilisis. Sed facilisis, ipsum vel ornare consequat, eros lacus vestibulum erat, quis mollis metus orci et
            lorem. Donec augue nulla, auctor efficitur turpis quis, hendrerit interdum metus. In ultricies, elit vel
            tincidunt iaculis, est enim aliquet augue, quis egestas metus libero at dui. Sed venenatis ipsum massa, in
            pulvinar arcu pretium nec. Nullam elementum malesuada tristique. Proin vel est quis dui consequat pretium.
            Etiam ac magna libero. In a odio ante. Vestibulum ac nibh mollis, viverra massa sit amet, cursus tortor.
            Duis porta risus ut luctus ultricies. Praesent convallis turpis nisl, vitae commodo purus varius et.
            Maecenas ac nunc dapibus, iaculis velit at, maximus mauris. Ut nibh ipsum, euismod at dui molestie, mollis
            sollicitudin dolor. Duis eleifend sollicitudin pretium. Duis semper ante condimentum ipsum hendrerit, id
            sollicitudin sapien pellentesque. Suspendisse nec pulvinar ante. Suspendisse augue ante, euismod non ornare
            in, fermentum non ipsum. Nulla suscipit, augue pellentesque pharetra volutpat, erat ipsum posuere elit, et
            ullamcorper eros nisi in elit. Suspendisse massa erat, gravida at dui ut, porttitor aliquam eros. Donec
            risus ex, iaculis vitae mi ac, molestie vulputate turpis. Aenean congue dictum ipsum nec efficitur. Nam
            suscipit odio et mattis rhoncus. Ut nulla lectus, aliquet eu pretium eu, suscipit scelerisque mi. Donec
            blandit arcu at arcu pretium, id congue neque lobortis. Morbi efficitur lorem at congue gravida. Integer
            semper blandit odio, a consectetur nisi rhoncus dapibus. Nam eu elementum magna. Curabitur eget convallis
            nibh. Sed interdum eros non feugiat tristique. Suspendisse volutpat mi quis purus blandit rhoncus. Sed nec
            lobortis felis. Quisque scelerisque massa quis purus accumsan vestibulum non tempus turpis. Nam fringilla
            facilisis tortor id ultrices. Donec hendrerit efficitur leo, convallis tempor lorem maximus at. Vivamus
            gravida nunc ex, eu pulvinar arcu tincidunt consectetur. Quisque cursus elit vitae massa molestie cursus
            eget hendrerit massa. Sed accumsan nibh nisi, vitae condimentum tortor tempus non. Aenean pulvinar orci
            augue, sit amet porta urna porttitor id. Sed mollis interdum dui sit amet viverra. Vestibulum sed vulputate
            odio, vel fermentum ligula. Morbi laoreet a nisl ac sollicitudin. Orci varius natoque penatibus et magnis
            dis parturient montes, nascetur ridiculus mus. Nam cursus pulvinar efficitur. Sed eget ante tortor. Integer
            vel dui vel est rutrum gravida. Curabitur elementum placerat tempor. Quisque rutrum finibus iaculis.
            Pellentesque vulputate egestas ligula, at interdum nibh lobortis vel. Proin lectus ipsum, gravida id
            vehicula a, pretium quis urna. Maecenas eget sem tincidunt, pharetra urna feugiat, finibus mi. Maecenas
            efficitur, magna at lobortis ullamcorper, dui libero venenatis urna, nec luctus velit justo id sem. Nam
            convallis feugiat eros in interdum. Proin in odio ultrices, eleifend libero a, lacinia nisl. Aliquam erat
            volutpat. Mauris lacinia nulla massa, sed scelerisque ipsum scelerisque ut. Cras mollis erat id interdum
            porttitor. Nulla a sodales elit. Integer viverra elit at nulla cursus, ac fringilla neque ornare. Cras
            posuere et mi non rutrum. Cras placerat felis non placerat tempor. Mauris eget lectus dictum, euismod arcu
            et, elementum massa.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque luctus urna id dui
            maximus iaculis. Vivamus lacinia sodales consectetur. Praesent finibus, felis ac lobortis egestas, purus
            ligula hendrerit odio, id fringilla ligula velit ut magna. Quisque eget nisi in augue interdum faucibus. In
            consectetur suscipit tincidunt. Quisque sed est velit. Proin tempor purus vel justo sollicitudin, eu
            vestibulum lacus viverra. Phasellus malesuada fermentum ultrices. Fusce eget dui pharetra, varius mi ac,
            tincidunt risus. Pellentesque id posuere tellus. Nulla sed quam vitae dui pellentesque finibus. Donec non
            nisl felis. Nullam aliquet congue tempus. Cras hendrerit neque quis enim malesuada viverra. Fusce vel dolor
            id nisi convallis elementum. Aliquam ut est magna. Etiam egestas molestie pretium. Nunc tristique odio eu
            eros vulputate, quis consequat tortor viverra. Donec ultricies luctus massa, in mattis orci molestie in.
            Morbi sagittis consectetur diam. In sed metus ut diam cursus placerat nec sed urna. Donec eu vehicula
            lectus. Nullam consectetur posuere turpis, nec porttitor arcu sollicitudin eu. Nunc placerat velit quam, nec
            auctor odio cursus viverra. Donec quis aliquam ipsum. Etiam quis blandit sapien. Maecenas fermentum laoreet
            volutpat. In ut semper erat. Aenean semper leo lorem, non molestie risus congue eu. In varius ligula eu
            pretium tempor. Morbi in quam efficitur, commodo ligula sed, pulvinar ex. Donec semper, ex vel sagittis
            pulvinar, ex neque ornare leo, eget euismod lacus lorem ac tellus. Nullam congue feugiat sem nec lobortis.
            Phasellus consectetur sollicitudin bibendum. Suspendisse tortor nisi, pretium eget tincidunt quis,
            pellentesque ac magna. Proin ac nisi quis elit porta imperdiet sed eget nisl. Cras feugiat auctor risus, vel
            scelerisque mi pulvinar quis. Nulla molestie vestibulum velit. Vestibulum porttitor ipsum purus, non aliquet
            felis porta vel. Suspendisse imperdiet posuere odio, in vehicula elit feugiat vitae. Pellentesque vulputate
            fermentum egestas. In finibus lorem rutrum ornare placerat. Sed id nunc quis erat venenatis dapibus. Quisque
            sed diam in nibh finibus rhoncus vitae id nisi. Morbi luctus nulla vitae lobortis efficitur. Etiam mollis
            nisl a dolor venenatis, vitae luctus erat pulvinar. Suspendisse enim nisl, lobortis id tortor ut, egestas
            placerat ipsum. Phasellus eros justo, sodales et dui quis, accumsan tincidunt justo. Vestibulum ante ipsum
            primis in faucibus orci luctus et ultrices posuere cubilia curae; Phasellus semper enim nec consectetur
            facilisis. Sed facilisis, ipsum vel ornare consequat, eros lacus vestibulum erat, quis mollis metus orci et
            lorem. Donec augue nulla, auctor efficitur turpis quis, hendrerit interdum metus. In ultricies, elit vel
            tincidunt iaculis, est enim aliquet augue, quis egestas metus libero at dui. Sed venenatis ipsum massa, in
            pulvinar arcu pretium nec. Nullam elementum malesuada tristique. Proin vel est quis dui consequat pretium.
            Etiam ac magna libero. In a odio ante. Vestibulum ac nibh mollis, viverra massa sit amet, cursus tortor.
            Duis porta risus ut luctus ultricies. Praesent convallis turpis nisl, vitae commodo purus varius et.
            Maecenas ac nunc dapibus, iaculis velit at, maximus mauris. Ut nibh ipsum, euismod at dui molestie, mollis
            sollicitudin dolor. Duis eleifend sollicitudin pretium. Duis semper ante condimentum ipsum hendrerit, id
            sollicitudin sapien pellentesque. Suspendisse nec pulvinar ante. Suspendisse augue ante, euismod non ornare
            in, fermentum non ipsum. Nulla suscipit, augue pellentesque pharetra volutpat, erat ipsum posuere elit, et
            ullamcorper eros nisi in elit. Suspendisse massa erat, gravida at dui ut, porttitor aliquam eros. Donec
            risus ex, iaculis vitae mi ac, molestie vulputate turpis. Aenean congue dictum ipsum nec efficitur. Nam
            suscipit odio et mattis rhoncus. Ut nulla lectus, aliquet eu pretium eu, suscipit scelerisque mi. Donec
            blandit arcu at arcu pretium, id congue neque lobortis. Morbi efficitur lorem at congue gravida. Integer
            semper blandit odio, a consectetur nisi rhoncus dapibus. Nam eu elementum magna. Curabitur eget convallis
            nibh. Sed interdum eros non feugiat tristique. Suspendisse volutpat mi quis purus blandit rhoncus. Sed nec
            lobortis felis. Quisque scelerisque massa quis purus accumsan vestibulum non tempus turpis. Nam fringilla
            facilisis tortor id ultrices. Donec hendrerit efficitur leo, convallis tempor lorem maximus at. Vivamus
            gravida nunc ex, eu pulvinar arcu tincidunt consectetur. Quisque cursus elit vitae massa molestie cursus
            eget hendrerit massa. Sed accumsan nibh nisi, vitae condimentum tortor tempus non. Aenean pulvinar orci
            augue, sit amet porta urna porttitor id. Sed mollis interdum dui sit amet viverra. Vestibulum sed vulputate
            odio, vel fermentum ligula. Morbi laoreet a nisl ac sollicitudin. Orci varius natoque penatibus et magnis
            dis parturient montes, nascetur ridiculus mus. Nam cursus pulvinar efficitur. Sed eget ante tortor. Integer
            vel dui vel est rutrum gravida. Curabitur elementum placerat tempor. Quisque rutrum finibus iaculis.
            Pellentesque vulputate egestas ligula, at interdum nibh lobortis vel. Proin lectus ipsum, gravida id
            vehicula a, pretium quis urna. Maecenas eget sem tincidunt, pharetra urna feugiat, finibus mi. Maecenas
            efficitur, magna at lobortis ullamcorper, dui libero venenatis urna, nec luctus velit justo id sem. Nam
            convallis feugiat eros in interdum. Proin in odio ultrices, eleifend libero a, lacinia nisl. Aliquam erat
            volutpat. Mauris lacinia nulla massa, sed scelerisque ipsum scelerisque ut. Cras mollis erat id interdum
            porttitor. Nulla a sodales elit. Integer viverra elit at nulla cursus, ac fringilla neque ornare. Cras
            posuere et mi non rutrum. Cras placerat felis non placerat tempor. Mauris eget lectus dictum, euismod arcu
            et, elementum massa.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque luctus urna id dui
            maximus iaculis. Vivamus lacinia sodales consectetur. Praesent finibus, felis ac lobortis egestas, purus
            ligula hendrerit odio, id fringilla ligula velit ut magna. Quisque eget nisi in augue interdum faucibus. In
            consectetur suscipit tincidunt. Quisque sed est velit. Proin tempor purus vel justo sollicitudin, eu
            vestibulum lacus viverra. Phasellus malesuada fermentum ultrices. Fusce eget dui pharetra, varius mi ac,
            tincidunt risus. Pellentesque id posuere tellus. Nulla sed quam vitae dui pellentesque finibus. Donec non
            nisl felis. Nullam aliquet congue tempus. Cras hendrerit neque quis enim malesuada viverra. Fusce vel dolor
            id nisi convallis elementum. Aliquam ut est magna. Etiam egestas molestie pretium. Nunc tristique odio eu
            eros vulputate, quis consequat tortor viverra. Donec ultricies luctus massa, in mattis orci molestie in.
            Morbi sagittis consectetur diam. In sed metus ut diam cursus placerat nec sed urna. Donec eu vehicula
            lectus. Nullam consectetur posuere turpis, nec porttitor arcu sollicitudin eu. Nunc placerat velit quam, nec
            auctor odio cursus viverra. Donec quis aliquam ipsum. Etiam quis blandit sapien. Maecenas fermentum laoreet
            volutpat. In ut semper erat. Aenean semper leo lorem, non molestie risus congue eu. In varius ligula eu
            pretium tempor. Morbi in quam efficitur, commodo ligula sed, pulvinar ex. Donec semper, ex vel sagittis
            pulvinar, ex neque ornare leo, eget euismod lacus lorem ac tellus. Nullam congue feugiat sem nec lobortis.
            Phasellus consectetur sollicitudin bibendum. Suspendisse tortor nisi, pretium eget tincidunt quis,
            pellentesque ac magna. Proin ac nisi quis elit porta imperdiet sed eget nisl. Cras feugiat auctor risus, vel
            scelerisque mi pulvinar quis. Nulla molestie vestibulum velit. Vestibulum porttitor ipsum purus, non aliquet
            felis porta vel. Suspendisse imperdiet posuere odio, in vehicula elit feugiat vitae. Pellentesque vulputate
            fermentum egestas. In finibus lorem rutrum ornare placerat. Sed id nunc quis erat venenatis dapibus. Quisque
            sed diam in nibh finibus rhoncus vitae id nisi. Morbi luctus nulla vitae lobortis efficitur. Etiam mollis
            nisl a dolor venenatis, vitae luctus erat pulvinar. Suspendisse enim nisl, lobortis id tortor ut, egestas
            placerat ipsum. Phasellus eros justo, sodales et dui quis, accumsan tincidunt justo. Vestibulum ante ipsum
            primis in faucibus orci luctus et ultrices posuere cubilia curae; Phasellus semper enim nec consectetur
            facilisis. Sed facilisis, ipsum vel ornare consequat, eros lacus vestibulum erat, quis mollis metus orci et
            lorem. Donec augue nulla, auctor efficitur turpis quis, hendrerit interdum metus. In ultricies, elit vel
            tincidunt iaculis, est enim aliquet augue, quis egestas metus libero at dui. Sed venenatis ipsum massa, in
            pulvinar arcu pretium nec. Nullam elementum malesuada tristique. Proin vel est quis dui consequat pretium.
            Etiam ac magna libero. In a odio ante. Vestibulum ac nibh mollis, viverra massa sit amet, cursus tortor.
            Duis porta risus ut luctus ultricies. Praesent convallis turpis nisl, vitae commodo purus varius et.
            Maecenas ac nunc dapibus, iaculis velit at, maximus mauris. Ut nibh ipsum, euismod at dui molestie, mollis
            sollicitudin dolor. Duis eleifend sollicitudin pretium. Duis semper ante condimentum ipsum hendrerit, id
            sollicitudin sapien pellentesque. Suspendisse nec pulvinar ante. Suspendisse augue ante, euismod non ornare
            in, fermentum non ipsum. Nulla suscipit, augue pellentesque pharetra volutpat, erat ipsum posuere elit, et
            ullamcorper eros nisi in elit. Suspendisse massa erat, gravida at dui ut, porttitor aliquam eros. Donec
            risus ex, iaculis vitae mi ac, molestie vulputate turpis. Aenean congue dictum ipsum nec efficitur. Nam
            suscipit odio et mattis rhoncus. Ut nulla lectus, aliquet eu pretium eu, suscipit scelerisque mi. Donec
            blandit arcu at arcu pretium, id congue neque lobortis. Morbi efficitur lorem at congue gravida. Integer
            semper blandit odio, a consectetur nisi rhoncus dapibus. Nam eu elementum magna. Curabitur eget convallis
            nibh. Sed interdum eros non feugiat tristique. Suspendisse volutpat mi quis purus blandit rhoncus. Sed nec
            lobortis felis. Quisque scelerisque massa quis purus accumsan vestibulum non tempus turpis. Nam fringilla
            facilisis tortor id ultrices. Donec hendrerit efficitur leo, convallis tempor lorem maximus at. Vivamus
            gravida nunc ex, eu pulvinar arcu tincidunt consectetur. Quisque cursus elit vitae massa molestie cursus
            eget hendrerit massa. Sed accumsan nibh nisi, vitae condimentum tortor tempus non. Aenean pulvinar orci
            augue, sit amet porta urna porttitor id. Sed mollis interdum dui sit amet viverra. Vestibulum sed vulputate
            odio, vel fermentum ligula. Morbi laoreet a nisl ac sollicitudin. Orci varius natoque penatibus et magnis
            dis parturient montes, nascetur ridiculus mus. Nam cursus pulvinar efficitur. Sed eget ante tortor. Integer
            vel dui vel est rutrum gravida. Curabitur elementum placerat tempor. Quisque rutrum finibus iaculis.
            Pellentesque vulputate egestas ligula, at interdum nibh lobortis vel. Proin lectus ipsum, gravida id
            vehicula a, pretium quis urna. Maecenas eget sem tincidunt, pharetra urna feugiat, finibus mi. Maecenas
            efficitur, magna at lobortis ullamcorper, dui libero venenatis urna, nec luctus velit justo id sem. Nam
            convallis feugiat eros in interdum. Proin in odio ultrices, eleifend libero a, lacinia nisl. Aliquam erat
            volutpat. Mauris lacinia nulla massa, sed scelerisque ipsum scelerisque ut. Cras mollis erat id interdum
            porttitor. Nulla a sodales elit. Integer viverra elit at nulla cursus, ac fringilla neque ornare. Cras
            posuere et mi non rutrum. Cras placerat felis non placerat tempor. Mauris eget lectus dictum, euismod arcu
            et, elementum massa.Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque luctus urna id dui
            maximus iaculis. Vivamus lacinia sodales consectetur. Praesent finibus, felis ac lobortis egestas, purus
            ligula hendrerit odio, id fringilla ligula velit ut magna. Quisque eget nisi in augue interdum faucibus. In
            consectetur suscipit tincidunt. Quisque sed est velit. Proin tempor purus vel justo sollicitudin, eu
            vestibulum lacus viverra. Phasellus malesuada fermentum ultrices. Fusce eget dui pharetra, varius mi ac,
            tincidunt risus. Pellentesque id posuere tellus. Nulla sed quam vitae dui pellentesque finibus. Donec non
            nisl felis. Nullam aliquet congue tempus. Cras hendrerit neque quis enim malesuada viverra. Fusce vel dolor
            id nisi convallis elementum. Aliquam ut est magna. Etiam egestas molestie pretium. Nunc tristique odio eu
            eros vulputate, quis consequat tortor viverra. Donec ultricies luctus massa, in mattis orci molestie in.
            Morbi sagittis consectetur diam. In sed metus ut diam cursus placerat nec sed urna. Donec eu vehicula
            lectus. Nullam consectetur posuere turpis, nec porttitor arcu sollicitudin eu. Nunc placerat velit quam, nec
            auctor odio cursus viverra. Donec quis aliquam ipsum. Etiam quis blandit sapien. Maecenas fermentum laoreet
            volutpat. In ut semper erat. Aenean semper leo lorem, non molestie risus congue eu. In varius ligula eu
            pretium tempor. Morbi in quam efficitur, commodo ligula sed, pulvinar ex. Donec semper, ex vel sagittis
            pulvinar, ex neque ornare leo, eget euismod lacus lorem ac tellus. Nullam congue feugiat sem nec lobortis.
            Phasellus consectetur sollicitudin bibendum. Suspendisse tortor nisi, pretium eget tincidunt quis,
            pellentesque ac magna. Proin ac nisi quis elit porta imperdiet sed eget nisl. Cras feugiat auctor risus, vel
            scelerisque mi pulvinar quis. Nulla molestie vestibulum velit. Vestibulum porttitor ipsum purus, non aliquet
            felis porta vel. Suspendisse imperdiet posuere odio, in vehicula elit feugiat vitae. Pellentesque vulputate
            fermentum egestas. In finibus lorem rutrum ornare placerat. Sed id nunc quis erat venenatis dapibus. Quisque
            sed diam in nibh finibus rhoncus vitae id nisi. Morbi luctus nulla vitae lobortis efficitur. Etiam mollis
            nisl a dolor venenatis, vitae luctus erat pulvinar. Suspendisse enim nisl, lobortis id tortor ut, egestas
            placerat ipsum. Phasellus eros justo, sodales et dui quis, accumsan tincidunt justo. Vestibulum ante ipsum
            primis in faucibus orci luctus et ultrices posuere cubilia curae; Phasellus semper enim nec consectetur
            facilisis. Sed facilisis, ipsum vel ornare consequat, eros lacus vestibulum erat, quis mollis metus orci et
            lorem. Donec augue nulla, auctor efficitur turpis quis, hendrerit interdum metus. In ultricies, elit vel
            tincidunt iaculis, est enim aliquet augue, quis egestas metus libero at dui. Sed venenatis ipsum massa, in
            pulvinar arcu pretium nec. Nullam elementum malesuada tristique. Proin vel est quis dui consequat pretium.
            Etiam ac magna libero. In a odio ante. Vestibulum ac nibh mollis, viverra massa sit amet, cursus tortor.
            Duis porta risus ut luctus ultricies. Praesent convallis turpis nisl, vitae commodo purus varius et.
            Maecenas ac nunc dapibus, iaculis velit at, maximus mauris. Ut nibh ipsum, euismod at dui molestie, mollis
            sollicitudin dolor. Duis eleifend sollicitudin pretium. Duis semper ante condimentum ipsum hendrerit, id
            sollicitudin sapien pellentesque. Suspendisse nec pulvinar ante. Suspendisse augue ante, euismod non ornare
            in, fermentum non ipsum. Nulla suscipit, augue pellentesque pharetra volutpat, erat ipsum posuere elit, et
            ullamcorper eros nisi in elit. Suspendisse massa erat, gravida at dui ut, porttitor aliquam eros. Donec
            risus ex, iaculis vitae mi ac, molestie vulputate turpis. Aenean congue dictum ipsum nec efficitur. Nam
            suscipit odio et mattis rhoncus. Ut nulla lectus, aliquet eu pretium eu, suscipit scelerisque mi. Donec
            blandit arcu at arcu pretium, id congue neque lobortis. Morbi efficitur lorem at congue gravida. Integer
            semper blandit odio, a consectetur nisi rhoncus dapibus. Nam eu elementum magna. Curabitur eget convallis
            nibh. Sed interdum eros non feugiat tristique. Suspendisse volutpat mi quis purus blandit rhoncus. Sed nec
            lobortis felis. Quisque scelerisque massa quis purus accumsan vestibulum non tempus turpis. Nam fringilla
            facilisis tortor id ultrices. Donec hendrerit efficitur leo, convallis tempor lorem maximus at. Vivamus
            gravida nunc ex, eu pulvinar arcu tincidunt consectetur. Quisque cursus elit vitae massa molestie cursus
            eget hendrerit massa. Sed accumsan nibh nisi, vitae condimentum tortor tempus non. Aenean pulvinar orci
            augue, sit amet porta urna porttitor id. Sed mollis interdum dui sit amet viverra. Vestibulum sed vulputate
            odio, vel fermentum ligula. Morbi laoreet a nisl ac sollicitudin. Orci varius natoque penatibus et magnis
            dis parturient montes, nascetur ridiculus mus. Nam cursus pulvinar efficitur. Sed eget ante tortor. Integer
            vel dui vel est rutrum gravida. Curabitur elementum placerat tempor. Quisque rutrum finibus iaculis.
            Pellentesque vulputate egestas ligula, at interdum nibh lobortis vel. Proin lectus ipsum, gravida id
            vehicula a, pretium quis urna. Maecenas eget sem tincidunt, pharetra urna feugiat, finibus mi. Maecenas
            efficitur, magna at lobortis ullamcorper, dui libero venenatis urna, nec luctus velit justo id sem. Nam
            convallis feugiat eros in interdum. Proin in odio ultrices, eleifend libero a, lacinia nisl. Aliquam erat
            volutpat. Mauris lacinia nulla massa, sed scelerisque ipsum scelerisque ut. Cras mollis erat id interdum
            porttitor. Nulla a sodales elit. Integer viverra elit at nulla cursus, ac fringilla neque ornare. Cras
            posuere et mi non rutrum. Cras placerat felis non placerat tempor. Mauris eget lectus dictum, euismod arcu
            et, elementum massa.
        </div>
    </AdminLayout>
</template>
