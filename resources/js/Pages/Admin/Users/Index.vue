<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';

const props = defineProps({
    users: Object, // Laravel's paginated response
    roles: Array,  // Pass this from controller
    filters: Object,
});

const search = ref(props.filters.search);
const roleFilter = ref(props.filters.role);

// Debounce search input to avoid spamming requests
watch([search, roleFilter], debounce(function ([searchValue, roleValue]) {
    router.get(route('admin.users.index'), {
        search: searchValue,
        role: roleValue,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300));

// Form for assigning roles
const roleForm = useForm({
    role: '',
});

const selectedUser = ref(null);
const showRoleModal = ref(false);

const openRoleModal = (user) => {
    selectedUser.value = user;
    // Set the current role in the form
    roleForm.role = user.roles.length > 0 ? user.roles[0] : '';
    showRoleModal.value = true;
};

const submitRoleChange = () => {
    roleForm.put(route('admin.users.update', selectedUser.value.id), {
        onSuccess: () => {
            showRoleModal.value = false;
            roleForm.reset();
        },
    });
};

const deactivateUser = (user) => {
    if (confirm(`Are you sure you want to deactivate ${user.profile.full_name}?`)) {
        router.delete(route('admin.users.destroy', user.id), {
            preserveScroll: true,
        });
    }
};

</script>

<template>
    <Head title="User Management" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">User Management</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Filters -->
                        <div class="flex space-x-4 mb-4">
                            <input type="text" v-model="search" placeholder="Search by name or email..." class="border-gray-300 rounded-md shadow-sm">
                            <select v-model="roleFilter" class="border-gray-300 rounded-md shadow-sm">
                                <option value="">All Roles</option>
                                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                            </select>
                        </div>

                        <!-- Users Table -->
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="user in users.data" :key="user.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ user.profile.full_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ user.email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="user.roles.length" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ user.roles[0] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="openRoleModal(user)" class="text-indigo-600 hover:text-indigo-900 mr-4">Change Role</button>
                                        <button @click="deactivateUser(user)" class="text-red-600 hover:text-red-900">Deactivate</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="mt-4 flex justify-between items-center">
                            <!-- Pagination info can go here -->
                            <div class="flex space-x-1">
                                <Link v-for="link in users.links" :key="link.label"
                                      :href="link.url"
                                      v-html="link.label"
                                      class="px-3 py-2 border rounded"
                                      :class="{ 'bg-blue-500 text-white': link.active, 'text-gray-700': !link.active }"
                                      :disabled="!link.url">
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Change Modal -->
        <div v-if="showRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" @click.self="showRoleModal = false">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Change Role for {{ selectedUser.profile.full_name }}</h3>
                    <div class="mt-2 px-7 py-3">
                        <select v-model="roleForm.role" class="w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">-- No Role --</option>
                            <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                        </select>
                         <p v-if="roleForm.errors.role" class="text-sm text-red-600 mt-1">{{ roleForm.errors.role }}</p>
                    </div>
                    <div class="items-center px-4 py-3">
                        <button @click="submitRoleChange" :disabled="roleForm.processing" class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
