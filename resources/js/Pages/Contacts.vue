<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref, onMounted, onBeforeUnmount } from "vue";
import { usePage } from "@inertiajs/vue3";
import axios from "axios";
import Peer from "peerjs";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";

defineProps({
    auth: Object,
    users: Array,
});

const auth = usePage().props.auth;
const users = usePage().props.users;
const selectedUser = ref(null);
const peer = new Peer();
const peerCall = ref(null);
const remoteVideo = ref(null);
const localVideo = ref(null);
const isCalling = ref(false);
const localStream = ref(null);
const incomingCall = ref(null);
const incomingEvent = ref(null);
const isMuted = ref(false);
const cameraOff = ref(false);

const callUser = () => {
    axios.post(`/video-call/request/${selectedUser.value.id}`, {
        peerId: peer.id,
    });
    isCalling.value = true;
    displayLocalVideo();
};

const endCall = () => {
    peerCall.value?.close();
    // stop and release any active media tracks
    localStream.value?.getTracks().forEach((track) => track.stop());
    if (localVideo.value?.srcObject) {
        localVideo.value.srcObject.getTracks().forEach((t) => t.stop());
        localVideo.value.srcObject = null;
    }
    if (remoteVideo.value?.srcObject) {
        remoteVideo.value.srcObject.getTracks().forEach((t) => t.stop());
        remoteVideo.value.srcObject = null;
    }
    localStream.value = null;
    peerCall.value = null;
    isCalling.value = false;
    isMuted.value = false;
    cameraOff.value = false;
};

const displayLocalVideo = () => {
    navigator.mediaDevices
        .getUserMedia({ video: true, audio: true })
        .then((stream) => {
            localVideo.value.srcObject = stream;
            localStream.value = stream;
        })
        .catch((err) => {
            console.error("Error accessing media devices:", err);
        });
};

const toggleMic = () => {
    if (!localStream.value) return;
    localStream.value.getAudioTracks().forEach((t) => (t.enabled = !t.enabled));
    isMuted.value = !isMuted.value;
};

const toggleCamera = () => {
    if (!localStream.value) return;
    localStream.value.getVideoTracks().forEach((t) => (t.enabled = !t.enabled));
    cameraOff.value = !cameraOff.value;
};

const setSelectedUser = (user) => {
    selectedUser.value = user;
};

const recipientAcceptCall = (e) => {
    // send signal that recipient accept the call
    axios.post(`/video-call/request/status/${e.user.fromUser.id}`, {
        peerId: peer.id,
        status: "accept",
    });

    // stand by for callers connection
    peer.on("call", (call) => {
        // will be used when ending a call
        peerCall.value = call;
        // accept call if the caller is the one that you accepted
        if (e.user.peerId == call.peer) {
            // Prompt user to allow media devices
            navigator.mediaDevices
                .getUserMedia({ video: true, audio: true })
                .then((stream) => {
                    // Answer the call with your stream
                    call.answer(stream);
                    // Listen for the caller's stream
                    call.on("stream", (remoteStream) => {
                        remoteVideo.value.srcObject = remoteStream;
                    });

                    // caller end the call
                    call.on("close", () => {
                        endCall();
                    });
                })
                .catch((err) => {
                    console.error("Error accessing media devices:", err);
                });
        }
    });
};

const createConnection = (e) => {
    let receiverId = e.user.peerId;
    navigator.mediaDevices
        .getUserMedia({ video: true, audio: true })
        .then((stream) => {
            // Initiate the call with the receiver's ID
            const call = peer.call(receiverId, stream);
            // will be used when ending a call
            peerCall.value = call;

            // Listen for the receiver's stream
            call.on("stream", (remoteStream) => {
                remoteVideo.value.srcObject = remoteStream;
            });

            // receiver end the call
            call.on("close", () => {
                endCall();
            });
        })
        .catch((err) => {
            console.error("Error accessing media devices:", err);
        });
};

const connectWebSocket = () => {
    // request video call
    window.Echo.private(`video-call.${auth.user.id}`).listen(
        "RequestVideoCall",
        (e) => {
            incomingCall.value = e.user.fromUser;
            incomingEvent.value = e;
        }
    );

    // video call request accepted
    window.Echo.private(`video-call.${auth.user.id}`).listen(
        "RequestVideoCallStatus",
        (e) => {
            if (e.user.status === "accept") {
                createConnection(e);
            } else {
                endCall();
            }
        }
    );
};

onMounted(() => {
    connectWebSocket();
});

onBeforeUnmount(() => {
    window.Echo.leave(`video-call.${auth.user.id}`);
    endCall();
});

const acceptCall = () => {
    if (!incomingEvent.value) return;
    selectedUser.value = incomingCall.value;
    isCalling.value = true;
    recipientAcceptCall(incomingEvent.value);
    displayLocalVideo();
    incomingCall.value = null;
    incomingEvent.value = null;
};

const declineCall = () => {
    if (!incomingEvent.value) {
        incomingCall.value = null;
        return;
    }
    axios.post(
        `/video-call/request/status/${incomingEvent.value.user.fromUser.id}`,
        {
            peerId: peer.id,
            status: "deny",
        }
    );
    incomingCall.value = null;
    incomingEvent.value = null;
};
</script>

<template>
    <Head title="Contacts" />
    <AuthenticatedLayout>
        <Modal :show="Boolean(incomingCall)" @close="declineCall">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Incoming call from {{ incomingCall?.name }}
                </h2>
                <div class="mt-4 flex justify-end space-x-4">
                    <PrimaryButton @click="acceptCall">Accept</PrimaryButton>
                    <DangerButton @click="declineCall">Decline</DangerButton>
                </div>
            </div>
        </Modal>
        <div
            class="h-screen flex bg-gray-100 mx-auto max-w-7xl sm:px-6 lg:px-8"
            style="height: 90vh"
        >
            <!-- Sidebar -->
            <div class="w-1/4 bg-white border-r border-gray-200">
                <div
                    class="p-4 bg-gray-100 font-bold text-lg border-b border-gray-200"
                >
                    Contacts
                </div>
                <div class="p-4 space-y-4">
                    <!-- Contact List -->
                    <div
                        v-for="(user, key) in users"
                        :key="key"
                        @click="setSelectedUser(user)"
                        :class="[
                            'flex items-center p-2 hover:bg-blue-500 hover:text-white rounded cursor-pointer',
                            user.id === selectedUser?.id
                                ? 'bg-blue-500 text-white'
                                : '',
                        ]"
                    >
                        <div class="w-12 h-12 bg-blue-200 rounded-full"></div>
                        <div class="ml-4">
                            <div class="font-semibold">{{ user.name }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Area -->
            <div class="flex flex-col w-3/4">
                <!-- No Conversation Selected -->
                <div
                    v-if="!selectedUser"
                    class="h-full flex justify-center items-center text-gray-800 font-bold"
                >
                    Select Contact
                </div>

                <template v-else>
                    <!-- Contact Header -->
                    <div class="p-4 border-b border-gray-200 flex items-center">
                        <div class="w-12 h-12 bg-blue-200 rounded-full"></div>
                        <div class="ml-4 font-bold flex-1">{{ selectedUser?.name }}</div>
                        <button
                            v-if="!isCalling"
                            @click="callUser"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                        >
                            Call
                        </button>
                    </div>

                    <div
                        class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 relative"
                    >
                        <template v-if="isCalling">
                            <div class="relative h-full w-full">
                                <video
                                    id="remoteVideo"
                                    ref="remoteVideo"
                                    autoplay
                                    playsinline
                                    muted
                                    class="border-2 border-gray-800 w-full h-full object-cover"
                                ></video>
                                <div class="absolute bottom-4 right-4 w-1/4 border-2 border-gray-800">
                                    <video
                                        id="localVideo"
                                        ref="localVideo"
                                        autoplay
                                        playsinline
                                        muted
                                        class="w-full h-full object-cover"
                                    ></video>
                                    <div
                                        v-if="cameraOff"
                                        class="absolute inset-0 bg-black/60 text-white flex items-center justify-center text-sm"
                                    >
                                        Camera Off
                                    </div>
                                </div>
                                <div
                                    class="absolute bottom-4 left-1/2 -translate-x-1/2 flex space-x-4 bg-white/70 rounded-lg p-2"
                                >
                                    <button
                                        @click="toggleMic"
                                        class="px-3 py-2 rounded-full bg-gray-200 hover:bg-gray-300"
                                    >
                                        {{ isMuted ? 'Unmute' : 'Mute' }}
                                    </button>
                                    <button
                                        @click="toggleCamera"
                                        class="px-3 py-2 rounded-full bg-gray-200 hover:bg-gray-300"
                                    >
                                        {{ cameraOff ? 'Show' : 'Hide' }}
                                    </button>
                                    <button
                                        @click="endCall"
                                        class="px-3 py-2 rounded-full bg-red-500 text-white hover:bg-red-600"
                                    >
                                        End
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div
                            v-if="!isCalling"
                            class="h-full flex justify-center items-center text-gray-800 font-bold"
                        >
                            No Ongoing Call.
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
