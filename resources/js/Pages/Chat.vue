<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, usePage } from "@inertiajs/vue3";
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import axios from "axios";

const auth = usePage().props.auth;
const users = ref(usePage().props.users);
const selectedUser = ref(null);
const messages = ref([]);
const newMessage = ref("");
const audioCtx = ref(null);

const groupedMessages = computed(() => {
    const result = [];
    let lastDate = null;
    messages.value.forEach((m) => {
        const dt = new Date(m.created_at);
        const dateLabel = dt.toLocaleDateString(undefined, {
            day: "numeric",
            month: "short",
            year: "numeric",
        });
        const timeLabel = dt.toLocaleTimeString([], {
            hour: "2-digit",
            minute: "2-digit",
        });
        if (lastDate !== dateLabel) {
            result.push({ type: "day", label: dateLabel });
            lastDate = dateLabel;
        }
        result.push({ type: "msg", data: { ...m, time: timeLabel } });
    });
    return result;
});

const playNotification = () => {
    if (audioCtx.value === null) {
        audioCtx.value = new (window.AudioContext || window.webkitAudioContext)();
    }
    const ctx = audioCtx.value;
    const oscillator = ctx.createOscillator();
    const gainNode = ctx.createGain();
    oscillator.connect(gainNode);
    gainNode.connect(ctx.destination);
    oscillator.type = "sine";
    oscillator.frequency.setValueAtTime(600, ctx.currentTime);
    oscillator.start();
    gainNode.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.2);
    oscillator.stop(ctx.currentTime + 0.2);
};

const fetchMessages = (user) => {
    axios.get(`/chat/messages/${user.id}`).then((res) => {
        messages.value = res.data;
        const u = users.value.find((usr) => usr.id === user.id);
        if (u) {
            u.has_unread = false;
        }
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
            const u = users.value.find((usr) => usr.id === selectedUser.value.id);
            if (u) {
                u.last_message = res.data;
                u.has_unread = false;
            }
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
                if (e.message.sender_id !== auth.user.id) {
                    playNotification();
                }
                const u = users.value.find((usr) => usr.id === selectedUser.value.id);
                if (u) {
                    u.last_message = e.message;
                    u.has_unread = false;
                }
            }
            const otherId =
                e.message.sender_id === auth.user.id
                    ? e.message.receiver_id
                    : e.message.sender_id;
            const otherUser = users.value.find((usr) => usr.id === otherId);
            if (otherUser) {
                otherUser.last_message = e.message;
                if (otherUser.id !== selectedUser.value?.id && e.message.sender_id !== auth.user.id) {
                    otherUser.has_unread = true;
                    playNotification();
                }
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
                            <div
                                :class="['text-sm', user.has_unread ? 'font-bold' : 'text-gray-500']"
                            >
                                <span v-if="user.last_message">
                                    <span v-if="user.last_message.sender_id === auth.user.id">You: </span>
                                    {{ user.last_message.content }}
                                </span>
                            </div>
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
                        <div v-for="(item, i) in groupedMessages" :key="i">
                            <div v-if="item.type === 'day'" class="text-center text-gray-500 my-2 font-semibold">
                                {{ item.label }}
                            </div>
                            <div
                                v-else
                                :class="item.data.sender_id === auth.user.id ? 'text-right' : 'text-left'"
                            >
                                <div
                                    :class="[
                                        'inline-block px-3 py-2 rounded-lg',
                                        item.data.sender_id === auth.user.id
                                            ? 'bg-blue-500 text-white'
                                            : 'bg-white border',
                                    ]"
                                >
                                    {{ item.data.content }}
                                    <div class="text-xs mt-1 flex items-center" :class="item.data.sender_id === auth.user.id ? 'justify-end' : 'justify-start'">
                                        <span class="mr-1">{{ item.data.time }}</span>
                                        <span v-if="item.data.sender_id === auth.user.id">
                                            {{ item.data.is_seen ? 'Seen' : 'Sent' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
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
