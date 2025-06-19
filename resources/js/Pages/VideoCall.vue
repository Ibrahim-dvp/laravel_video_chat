<template>
    <Head title="Video Call" />
    <AuthenticatedLayout>
        <div class="relative h-screen bg-gray-900 text-white overflow-hidden">
            <div class="absolute inset-0 flex items-center justify-center">
                <video ref="remoteVideo" autoplay playsinline class="w-full h-full object-cover bg-black"></video>
            </div>
            <div class="absolute bottom-4 right-4 w-1/4 border-2 border-white bg-black">
                <video ref="localVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
                <div v-if="cameraOff" class="absolute inset-0 bg-black/60 flex items-center justify-center text-sm">Camera Off</div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 flex justify-center space-x-2 p-4 bg-black/50">
                <select v-model="selectedAudio" class="text-black rounded px-2 py-1">
                    <option v-for="d in audioDevices" :key="d.deviceId" :value="d.deviceId">{{ d.label || `Mic ${d.deviceId}` }}</option>
                </select>
                <select v-model="selectedVideo" class="text-black rounded px-2 py-1">
                    <option v-for="d in videoDevices" :key="d.deviceId" :value="d.deviceId">{{ d.label || `Cam ${d.deviceId}` }}</option>
                </select>
                <button @click="toggleMic" class="p-2 rounded-full bg-gray-200 text-black hover:bg-gray-300">
                    <svg v-if="!isMuted" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2a3 3 0 00-3 3v6a3 3 0 006 0V5a3 3 0 00-3-3zm5 9a5 5 0 01-10 0m5 5v4m-4 0h8" />
                    </svg>
                    <svg v-else class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10v1a3 3 0 01-3 3m-3-3V5a3 3 0 016 0v4m6 6l-6-6m-6 0L3 3m9 13v5m-4 0h8" />
                    </svg>
                </button>
                <button @click="toggleCamera" class="p-2 rounded-full bg-gray-200 text-black hover:bg-gray-300">
                    <svg v-if="!cameraOff" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14V10z" />
                        <rect x="3" y="6" width="12" height="12" rx="2" ry="2" />
                    </svg>
                    <svg v-else class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14V10z" />
                        <rect x="3" y="6" width="12" height="12" rx="2" ry="2" />
                    </svg>
                </button>
                <button @click="endCall" class="p-2 rounded-full bg-red-600 hover:bg-red-700">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15s2-2 9-2 9 2 9 2v3H3v-3z" />
                    </svg>
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Peer from 'peerjs';
import { usePage } from '@inertiajs/vue3';

const auth = usePage().props.auth;
const peer = new Peer();
const remoteVideo = ref(null);
const localVideo = ref(null);
const peerCall = ref(null);
const localStream = ref(null);
const audioDevices = ref([]);
const videoDevices = ref([]);
const selectedAudio = ref('');
const selectedVideo = ref('');
const isMuted = ref(false);
const cameraOff = ref(false);

const enumerate = async () => {
    const devices = await navigator.mediaDevices.enumerateDevices();
    audioDevices.value = devices.filter(d => d.kind === 'audioinput');
    videoDevices.value = devices.filter(d => d.kind === 'videoinput');
    if (!selectedAudio.value && audioDevices.value.length) selectedAudio.value = audioDevices.value[0].deviceId;
    if (!selectedVideo.value && videoDevices.value.length) selectedVideo.value = videoDevices.value[0].deviceId;
};

const getMedia = async () => {
    const constraints = {
        audio: { deviceId: selectedAudio.value ? { exact: selectedAudio.value } : undefined },
        video: { deviceId: selectedVideo.value ? { exact: selectedVideo.value } : undefined }
    };
    const stream = await navigator.mediaDevices.getUserMedia(constraints);
    localStream.value = stream;
    if (localVideo.value) localVideo.value.srcObject = stream;
    return stream;
};

const toggleMic = () => {
    if (!localStream.value) return;
    localStream.value.getAudioTracks().forEach(t => t.enabled = !t.enabled);
    isMuted.value = !isMuted.value;
};

const toggleCamera = async () => {
    if (!localStream.value) return;
    cameraOff.value = !cameraOff.value;
    const videoSenders = peerCall.value?.peerConnection.getSenders().filter(s => s.track && s.track.kind === 'video') || [];
    if (cameraOff.value) {
        localStream.value.getVideoTracks().forEach(t => { t.enabled = false; t.stop(); localStream.value.removeTrack(t); });
        videoSenders.forEach(sender => sender.replaceTrack(null));
    } else {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { deviceId: selectedVideo.value ? { exact: selectedVideo.value } : undefined }, audio: false });
        const newTrack = stream.getVideoTracks()[0];
        localStream.value.addTrack(newTrack);
        videoSenders.forEach(sender => sender.replaceTrack(newTrack));
        if (localVideo.value) {
            const lvStream = localVideo.value.srcObject;
            if (lvStream) { lvStream.addTrack(newTrack); } else { localVideo.value.srcObject = new MediaStream([newTrack]); }
        }
    }
};

const endCall = () => {
    peerCall.value?.close();
    localStream.value?.getTracks().forEach(t => t.stop());
    if (localVideo.value?.srcObject) { localVideo.value.srcObject.getTracks().forEach(t => t.stop()); localVideo.value.srcObject = null; }
    if (remoteVideo.value?.srcObject) { remoteVideo.value.srcObject.getTracks().forEach(t => t.stop()); remoteVideo.value.srcObject = null; }
    localStream.value = null;
    peerCall.value = null;
    isMuted.value = false;
    cameraOff.value = false;
};

const connectWebSocket = () => {
    window.Echo.private(`video-call.${auth.user.id}`).listen('RequestVideoCall', e => {
        const call = peer.call(e.user.peerId, localStream.value);
        peerCall.value = call;
        call.on('stream', stream => { if (remoteVideo.value) remoteVideo.value.srcObject = stream; });
        call.on('close', endCall);
    });
};

watch([selectedAudio, selectedVideo], async () => {
    if (localStream.value) {
        await getMedia();
    }
});

onMounted(async () => {
    await enumerate();
    const stream = await getMedia();
    connectWebSocket();
    peer.on('call', call => {
        peerCall.value = call;
        call.answer(stream);
        call.on('stream', remote => { if (remoteVideo.value) remoteVideo.value.srcObject = remote; });
        call.on('close', endCall);
    });
});

onBeforeUnmount(() => {
    window.Echo.leave(`video-call.${auth.user.id}`);
    endCall();
});
</script>
