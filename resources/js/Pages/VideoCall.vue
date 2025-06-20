<template>
    <Head title="Video Call" />
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
        <div class="h-screen flex bg-gray-100 mx-auto max-w-7xl sm:px-6 lg:px-8" style="height: 90vh">
            <div class="w-1/4 bg-white border-r border-gray-200">
                <div class="p-4 bg-gray-100 font-bold text-lg border-b border-gray-200">Users</div>
                <div class="p-4 space-y-4">
                    <div
                        v-for="(user, key) in users"
                        :key="key"
                        @click="setSelectedUser(user)"
                        :class="[
                            'flex items-center p-2 hover:bg-blue-500 hover:text-white rounded cursor-pointer',
                            user.id === selectedUser?.id ? 'bg-blue-500 text-white' : ''
                        ]"
                    >
                        <div class="w-12 h-12 bg-blue-200 rounded-full"></div>
                        <div class="ml-4">
                            <div class="font-semibold">{{ user.name }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col w-3/4 relative">
                <div v-if="!selectedUser" class="h-full flex justify-center items-center text-gray-800 font-bold">
                    Select User
                </div>
                <template v-else>
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
                    <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 relative">
                        <template v-if="isCalling">
                            <div class="relative h-full w-full">
                                <video ref="remoteVideo" autoplay playsinline muted class="border-2 border-gray-800 w-full h-full object-cover"></video>
                                <div class="absolute bottom-4 right-4 w-1/4 border-2 border-gray-800">
                                    <video ref="localVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
                                    <div v-if="cameraOff" class="absolute inset-0 bg-black/60 text-white flex items-center justify-center text-sm">
                                        Camera Off
                                    </div>
                                </div>
                                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex space-x-2 bg-white/70 rounded-lg p-2 items-center">
                                    <select v-model="selectedAudio" class="text-black rounded px-2 py-1">
                                        <option v-for="d in audioDevices" :key="d.deviceId" :value="d.deviceId">
                                            {{ d.label || `Mic ${d.deviceId}` }}
                                        </option>
                                    </select>
                                    <select v-model="selectedVideo" class="text-black rounded px-2 py-1">
                                        <option v-for="d in videoDevices" :key="d.deviceId" :value="d.deviceId">
                                            {{ d.label || `Cam ${d.deviceId}` }}
                                        </option>
                                    </select>
                                    <button @click="toggleMic" class="p-2 rounded-full bg-gray-200 hover:bg-gray-300">
                                        <svg v-if="!isMuted" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2a3 3 0 00-3 3v6a3 3 0 006 0V5a3 3 0 00-3-3zm5 9a5 5 0 01-10 0m5 5v4m-4 0h8" />
                                        </svg>
                                        <svg v-else class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10v1a3 3 0 01-3 3m-3-3V5a3 3 0 016 0v4m6 6l-6-6m-6 0L3 3m9 13v5m-4 0h8" />
                                        </svg>
                                    </button>
                                    <button @click="toggleCamera" class="p-2 rounded-full bg-gray-200 hover:bg-gray-300">
                                        <svg v-if="!cameraOff" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14V10z" />
                                            <rect x="3" y="6" width="12" height="12" rx="2" ry="2" />
                                        </svg>
                                        <svg v-else class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14V10z" />
                                            <rect x="3" y="6" width="12" height="12" rx="2" ry="2" />
                                        </svg>
                                    </button>
                                    <button @click="endCall" class="p-2 rounded-full bg-red-500 text-white hover:bg-red-600">
                                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15s2-2 9-2 9 2 9 2v3H3v-3z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div v-if="!isCalling" class="h-full flex items-center justify-center text-gray-800 font-bold">
                            <span>{{ callStatus }}</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import { Head, usePage } from "@inertiajs/vue3";
import { ref, onMounted, onBeforeUnmount, watch } from "vue";
import Peer from "peerjs";
import axios from "axios";

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
const callTimer = ref(null);
const callStatus = ref("No Ongoing Call.");
const audioDevices = ref([]);
const videoDevices = ref([]);
const selectedAudio = ref("");
const selectedVideo = ref("");
const isMuted = ref(false);
const cameraOff = ref(false);

const enumerate = async () => {
    const devices = await navigator.mediaDevices.enumerateDevices();
    audioDevices.value = devices.filter((d) => d.kind === "audioinput");
    videoDevices.value = devices.filter((d) => d.kind === "videoinput");
    if (!selectedAudio.value && audioDevices.value.length)
        selectedAudio.value = audioDevices.value[0].deviceId;
    if (!selectedVideo.value && videoDevices.value.length)
        selectedVideo.value = videoDevices.value[0].deviceId;
};

const getMedia = async () => {
    const constraints = {
        audio: { deviceId: selectedAudio.value ? { exact: selectedAudio.value } : undefined },
        video: { deviceId: selectedVideo.value ? { exact: selectedVideo.value } : undefined },
    };
    const stream = await navigator.mediaDevices.getUserMedia(constraints);
    localStream.value = stream;
    if (localVideo.value) localVideo.value.srcObject = stream;
    return stream;
};

const startTimer = (user) => {
    clearTimer();
    callTimer.value = setTimeout(() => {
        axios.post(`/video-call/request/status/${user.id}`, {
            peerId: peer.id,
            status: "missed",
        });
        callStatus.value = "Call Missed";
        endCall();
    }, 10000);
};

const clearTimer = () => {
    if (callTimer.value) {
        clearTimeout(callTimer.value);
        callTimer.value = null;
    }
};

const callUser = async () => {
    await getMedia();
    axios.post(`/video-call/request/${selectedUser.value.id}`, { peerId: peer.id });
    isCalling.value = true;
    callStatus.value = "Calling...";
    startTimer(selectedUser.value);
};

const endCall = () => {
    clearTimer();
    peerCall.value?.close();
    localStream.value?.getTracks().forEach((t) => t.stop());
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

const toggleMic = () => {
    if (!localStream.value) return;
    localStream.value.getAudioTracks().forEach((t) => (t.enabled = !t.enabled));
    isMuted.value = !isMuted.value;
};

const toggleCamera = async () => {
    if (!localStream.value) return;
    cameraOff.value = !cameraOff.value;
    const videoSenders = peerCall.value?.peerConnection.getSenders().filter((s) => s.track && s.track.kind === "video") || [];
    if (cameraOff.value) {
        localStream.value.getVideoTracks().forEach((t) => {
            t.enabled = false;
            t.stop();
            localStream.value.removeTrack(t);
        });
        videoSenders.forEach((sender) => sender.replaceTrack(null));
    } else {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { deviceId: selectedVideo.value ? { exact: selectedVideo.value } : undefined },
            audio: false,
        });
        const newTrack = stream.getVideoTracks()[0];
        localStream.value.addTrack(newTrack);
        videoSenders.forEach((sender) => sender.replaceTrack(newTrack));
        if (localVideo.value) {
            const lvStream = localVideo.value.srcObject;
            if (lvStream) {
                lvStream.addTrack(newTrack);
            } else {
                localVideo.value.srcObject = new MediaStream([newTrack]);
            }
        }
    }
};

const setSelectedUser = (user) => {
    selectedUser.value = user;
    callStatus.value = "No Ongoing Call.";
};

const acceptCall = () => {
    if (!incomingEvent.value) return;
    selectedUser.value = incomingCall.value;
    isCalling.value = true;
    recipientAcceptCall(incomingEvent.value);
    callStatus.value = "In Call";
    clearTimer();
    getMedia();
    incomingCall.value = null;
    incomingEvent.value = null;
};

const declineCall = () => {
    if (!incomingEvent.value) {
        incomingCall.value = null;
        clearTimer();
        callStatus.value = "Call Declined";
        return;
    }
    axios.post(`/video-call/request/status/${incomingEvent.value.user.fromUser.id}`, {
        peerId: peer.id,
        status: "deny",
    });
    clearTimer();
    incomingCall.value = null;
    incomingEvent.value = null;
    callStatus.value = "Call Declined";
};

const recipientAcceptCall = (e) => {
    axios.post(`/video-call/request/status/${e.user.fromUser.id}`, {
        peerId: peer.id,
        status: "accept",
    });
    peer.on("call", (call) => {
        peerCall.value = call;
        if (e.user.peerId == call.peer) {
            navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then((stream) => {
                call.answer(stream);
                call.on("stream", (remoteStream) => {
                    remoteVideo.value.srcObject = remoteStream;
                });
                call.on("close", () => {
                    endCall();
                    callStatus.value = "Call Ended";
                });
            });
        }
    });
};

const createConnection = (e) => {
    let receiverId = e.user.peerId;
    navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then((stream) => {
        const call = peer.call(receiverId, stream);
        peerCall.value = call;
        call.on("stream", (remoteStream) => {
            remoteVideo.value.srcObject = remoteStream;
        });
        call.on("close", () => {
            endCall();
            callStatus.value = "Call Ended";
        });
    });
};

const connectWebSocket = () => {
    window.Echo.private(`video-call.${auth.user.id}`).listen("RequestVideoCall", (e) => {
        incomingCall.value = e.user.fromUser;
        incomingEvent.value = e;
        startTimer(e.user.fromUser);
    });
    window.Echo.private(`video-call.${auth.user.id}`).listen("RequestVideoCallStatus", (e) => {
        clearTimer();
        if (e.user.status === "accept") {
            createConnection(e);
            callStatus.value = "In Call";
            isCalling.value = true;
        } else {
            callStatus.value = e.user.status === "deny" ? "Call Declined" : "Call Missed";
            endCall();
        }
    });
};

watch([selectedAudio, selectedVideo], async () => {
    if (localStream.value) {
        await getMedia();
    }
});

onMounted(async () => {
    await enumerate();
    connectWebSocket();
    peer.on("call", (call) => {
        peerCall.value = call;
        getMedia().then((stream) => {
            call.answer(stream);
            call.on("stream", (remote) => {
                remoteVideo.value.srcObject = remote;
            });
            call.on("close", () => {
                endCall();
                callStatus.value = "Call Ended";
            });
        });
    });
});

onBeforeUnmount(() => {
    window.Echo.leave(`video-call.${auth.user.id}`);
    endCall();
});
</script>
