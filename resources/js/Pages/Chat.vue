<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, usePage } from "@inertiajs/vue3";
import { ref, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";

const auth = usePage().props.auth;
const users = usePage().props.users;
const selectedUser = ref(null);
const messages = ref([]);
const newMessage = ref("");

const fetchMessages = (user) => {
    axios.get(`/chat/messages/${user.id}`).then((res) => {
        messages.value = res.data;
    });
};

const selectUser = (user) => {
    selectedUser.value = user;
    fetchMessages(user);
};

const sendMessage = () => {
    if (!newMessage.value || !selectedUser.value) return;
    axios
        .post(`/chat/messages/${selectedUser.value.id}`, {
            message: newMessage.value,
        })
        .then((res) => {
            messages.value.push(res.data);
            newMessage.value = "";
        });
};

const connectWebSocket = () => {
    window.Echo.private(`chat.${auth.user.id}`).listen(
        "NewChatMessage",
        (e) => {
            if (
                selectedUser.value &&
                (e.message.sender_id === selectedUser.value.id ||
                    e.message.receiver_id === selectedUser.value.id)
            ) {
                messages.value.push(e.message);
            }
        }
    );
};

onMounted(connectWebSocket);
onBeforeUnmount(() => {
    window.Echo.leave(`chat.${auth.user.id}`);
});
</script>

<template>
    <Head title="Chat" />
    <AuthenticatedLayout>
        <div class="h-screen flex bg-gray-100 mx-auto max-w-7xl sm:px-6 lg:px-8" style="height: 90vh">
            <div class="w-1/4 bg-white border-r border-gray-200">
                <div class="p-4 bg-gray-100 font-bold text-lg border-b border-gray-200">Users</div>
                <div class="p-4 space-y-4">
                    <div
                        v-for="(user, key) in users"
                        :key="key"
                        @click="selectUser(user)"
                        :class="[
                            'flex items-center p-2 hover:bg-blue-500 hover:text-white rounded cursor-pointer',
                            user.id === selectedUser?.id ? 'bg-blue-500 text-white' : '',
                        ]"
                    >
                        <div class="w-12 h-12 bg-blue-200 rounded-full"></div>
                        <div class="ml-4">
                            <div class="font-semibold">{{ user.name }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col w-3/4">
                <div v-if="!selectedUser" class="h-full flex justify-center items-center text-gray-800 font-bold">
                    Select User
                </div>
                <template v-else>
                    <div class="p-4 border-b border-gray-200 flex items-center">
                        <div class="w-12 h-12 bg-blue-200 rounded-full"></div>
                        <div class="ml-4 font-bold flex-1">{{ selectedUser?.name }}</div>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 space-y-2 bg-gray-50">
                        <div v-for="(msg, i) in messages" :key="i" :class="msg.sender_id === auth.user.id ? 'text-right' : 'text-left'">
                            <span
                                :class="[
                                    'inline-block px-3 py-2 rounded-lg',
                                    msg.sender_id === auth.user.id
                                        ? 'bg-blue-500 text-white'
                                        : 'bg-white border',
                                ]"
                                >{{ msg.content }}</span
                            >
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-200 flex space-x-2">
                        <input
                            v-model="newMessage"
                            @keyup.enter="sendMessage"
                            type="text"
                            placeholder="Type your message"
                            class="flex-1 border rounded px-3 py-2"
                        />
                        <button @click="sendMessage" class="bg-blue-500 text-white px-4 py-2 rounded">Send</button>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
