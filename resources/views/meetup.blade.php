@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div id="meet">
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/strophe.js/1.2.16/strophe.min.js"></script>
<script src="{{ asset('js/strophe.disco.min.js') }}"></script>
<script src='https://meet.jit.si/external_api.js'></script>
<script type="text/javascript">
    function codeAddress(){
        const domain = 'meet.jit.si';
        const options = {
            roomName: '{{Auth::user()->name}}',
            width: 800,
            height: 800,
            parentNode: document.querySelector('#meet'),
            lang: 'ar',
            userInfo: {
                email: '{{Auth::user()->email}}',
                displayName: '{{Auth::user()->name}}'
            }
        };
        const api = new JitsiMeetExternalAPI(domain, options);
    }
    window.onload=codeAddress;
</script>

<script>
    /* global $, JitsiMeetJS */

    const options = {
        hosts: {
            domain: 'beta.meet.jit.si',
            muc: 'conference.beta.meet.jit.si', // FIXME: use XEP-0030
            focus: 'focus.beta.meet.jit.si',
            //domain: 'dev71.onlinetestingserver.com:5000',
            // muc: 'conference.dev71.onlinetestingserver.com:5000' // FIXME: use XEP-0030
        },

        //bosh: 'https://jitsi-meet.example.com/http-bind',
        bosh: 'https://dev71.onlinetestingserver.com:5000/http-bind', // FIXME: use xep-0156 for that

        // The name of client node advertised in XEP-0115 'c' stanza
        //clientNode: 'https://dev71.onlinetestingserver.com:5000'
        clientNode: 'http://jitsi.org/jitsimeet',
    };

    const confOptions = {
        openBridgeChannel: true
    };

    let connection = null;
    let isJoined = false;
    let room = 'h';

    let localTracks = [];
    const remoteTracks = {};

    /**
     * Handles local tracks.
     * @param tracks Array with JitsiTrack objects
     */
    function onLocalTracks(tracks) {
        localTracks = tracks;
        for (let i = 0; i < localTracks.length; i++) {
            localTracks[i].addEventListener(
                JitsiMeetJS.events.track.TRACK_AUDIO_LEVEL_CHANGED,
                audioLevel => console.log(`Audio Level local: ${audioLevel}`));
            localTracks[i].addEventListener(
                JitsiMeetJS.events.track.TRACK_MUTE_CHANGED,
                () => console.log('local track muted'));
            localTracks[i].addEventListener(
                JitsiMeetJS.events.track.LOCAL_TRACK_STOPPED,
                () => console.log('local track stoped'));
            localTracks[i].addEventListener(
                JitsiMeetJS.events.track.TRACK_AUDIO_OUTPUT_CHANGED,
                deviceId =>
                    console.log(
                        `track audio output device was changed to ${deviceId}`));
            if (localTracks[i].getType() === 'video') {
                $('body').append(`<video autoplay='1' id='localVideo${i}' />`);
                localTracks[i].attach($(`#meet${i}`)[0]);
            } else {
                $('body').append(
                    `<audio autoplay='1' muted='true' id='localAudio${i}' />`);
                localTracks[i].attach($(`#meet${i}`)[0]);
            }
            if (isJoined) {
                room.addTrack(localTracks[i]);
            }
        }
    }

    /**
     * Handles remote tracks
     * @param track JitsiTrack object
     */
    function onRemoteTrack(track) {
        if (track.isLocal()) {
            return;
        }
        const participant = track.getParticipantId();

        if (!remoteTracks[participant]) {
            remoteTracks[participant] = [];
        }
        const idx = remoteTracks[participant].push(track);

        track.addEventListener(
            JitsiMeetJS.events.track.TRACK_AUDIO_LEVEL_CHANGED,
            audioLevel => console.log(`Audio Level remote: ${audioLevel}`));
        track.addEventListener(
            JitsiMeetJS.events.track.TRACK_MUTE_CHANGED,
            () => console.log('remote track muted'));
        track.addEventListener(
            JitsiMeetJS.events.track.LOCAL_TRACK_STOPPED,
            () => console.log('remote track stoped'));
        track.addEventListener(JitsiMeetJS.events.track.TRACK_AUDIO_OUTPUT_CHANGED,
            deviceId =>
                console.log(
                    `track audio output device was changed to ${deviceId}`));
        const id = participant + track.getType() + idx;

        if (track.getType() === 'video') {
            $('body').append(
                `<video autoplay='1' id='${participant}video${idx}' />`);
        } else {
            $('body').append(
                `<audio autoplay='1' id='${participant}audio${idx}' />`);
        }
        track.attach($(`#${id}`)[0]);
    }

    /**
     * That function is executed when the conference is joined
     */
    function onConferenceJoined() {
        console.log('conference joined!');
        isJoined = true;
        for (let i = 0; i < localTracks.length; i++) {
            room.addTrack(localTracks[i]);
        }
    }

    /**
     *
     * @param id
     */
    function onUserLeft(id) {
        console.log('user left');
        if (!remoteTracks[id]) {
            return;
        }
        const tracks = remoteTracks[id];

        for (let i = 0; i < tracks.length; i++) {
            tracks[i].detach($(`#${id}${tracks[i].getType()}`));
        }
    }

    /**
     * That function is called when connection is established successfully
     */
    function onConnectionSuccess() {
        room = connection.initJitsiConference('conference', confOptions);
        room.on(JitsiMeetJS.events.conference.TRACK_ADDED, onRemoteTrack);
        room.on(JitsiMeetJS.events.conference.TRACK_REMOVED, track => {
            console.log(`track removed!!!${track}`);
        });
        room.on(
            JitsiMeetJS.events.conference.CONFERENCE_JOINED,
            onConferenceJoined);
        room.on(JitsiMeetJS.events.conference.USER_JOINED, id => {
            console.log('user join');
            remoteTracks[id] = [];
        });
        room.on(JitsiMeetJS.events.conference.USER_LEFT, onUserLeft);
        room.on(JitsiMeetJS.events.conference.TRACK_MUTE_CHANGED, track => {
            console.log(`${track.getType()} - ${track.isMuted()}`);
        });
        room.on(
            JitsiMeetJS.events.conference.DISPLAY_NAME_CHANGED,
            (userID, displayName) => console.log(`${userID} - ${displayName}`));
        room.on(
            JitsiMeetJS.events.conference.TRACK_AUDIO_LEVEL_CHANGED,
            (userID, audioLevel) => console.log(`${userID} - ${audioLevel}`));
        room.on(
            JitsiMeetJS.events.conference.PHONE_NUMBER_CHANGED,
            () => console.log(`${room.getPhoneNumber()} - ${room.getPhonePin()}`));
        room.join();
    }

    /**
     * This function is called when the connection fail.
     */
    function onConnectionFailed() {
        console.error('Connection Failed!');
    }

    /**
     * This function is called when the connection fail.
     */
    function onDeviceListChanged(devices) {
        console.info('current devices', devices);
    }

    /**
     * This function is called when we disconnect.
     */
    function disconnect() {
        console.log('disconnect!');
        connection.removeEventListener(
            JitsiMeetJS.events.connection.CONNECTION_ESTABLISHED,
            onConnectionSuccess);
        connection.removeEventListener(
            JitsiMeetJS.events.connection.CONNECTION_FAILED,
            onConnectionFailed);
        connection.removeEventListener(
            JitsiMeetJS.events.connection.CONNECTION_DISCONNECTED,
            disconnect);
    }

    /**
     *
     */
    function unload() {
        for (let i = 0; i < localTracks.length; i++) {
            localTracks[i].dispose();
        }
        room.leave();
        connection.disconnect();
    }

    let isVideo = true;

    /**
     *
     */
    function switchVideo() { // eslint-disable-line no-unused-vars
        isVideo = !isVideo;
        if (localTracks[1]) {
            localTracks[1].dispose();
            localTracks.pop();
        }
        JitsiMeetJS.createLocalTracks({
            devices: [ isVideo ? 'video' : 'desktop' ]
        })
            .then(tracks => {
                localTracks.push(tracks[0]);
                localTracks[1].addEventListener(
                    JitsiMeetJS.events.track.TRACK_MUTE_CHANGED,
                    () => console.log('local track muted'));
                localTracks[1].addEventListener(
                    JitsiMeetJS.events.track.LOCAL_TRACK_STOPPED,
                    () => console.log('local track stoped'));
                localTracks[1].attach($('#meet')[0]);
                room.addTrack(localTracks[1]);
            })
            .catch(error => console.log(error));
    }

    /**
     *
     * @param selected
     */
    function changeAudioOutput(selected) { // eslint-disable-line no-unused-vars
        JitsiMeetJS.mediaDevices.setAudioOutputDevice(selected.value);
    }

    $(window).bind('beforeunload', unload);
    $(window).bind('unload', unload);

    // JitsiMeetJS.setLogLevel(JitsiMeetJS.logLevels.ERROR);
    const initOptions = {
        disableAudioLevels: true,

        // The ID of the jidesha extension for Chrome.
        desktopSharingChromeExtId: 'mbocklcggfhnbahlnepmldehdhpjfcjp',

        // Whether desktop sharing should be disabled on Chrome.
        desktopSharingChromeDisabled: false,

        // The media sources to use when using screen sharing with the Chrome
        // extension.
        desktopSharingChromeSources: [ 'screen', 'window' ],

        // Required version of Chrome extension
        desktopSharingChromeMinExtVersion: '0.1',

        // Whether desktop sharing should be disabled on Firefox.
        desktopSharingFirefoxDisabled: true
    };

    JitsiMeetJS.init(initOptions);

    connection = new JitsiMeetJS.JitsiConnection(null, null, options);

    connection.addEventListener(
        JitsiMeetJS.events.connection.CONNECTION_ESTABLISHED,
        onConnectionSuccess);
    connection.addEventListener(
        JitsiMeetJS.events.connection.CONNECTION_FAILED,
        onConnectionFailed);
    connection.addEventListener(
        JitsiMeetJS.events.connection.CONNECTION_DISCONNECTED,
        disconnect);

    JitsiMeetJS.mediaDevices.addEventListener(
        JitsiMeetJS.events.mediaDevices.DEVICE_LIST_CHANGED,
        onDeviceListChanged);

    connection.connect();

    JitsiMeetJS.createLocalTracks({ devices: [ 'audio', 'video' ] })
        .then(onLocalTracks)
        .catch(error => {
            throw error;
        });

    if (JitsiMeetJS.mediaDevices.isDeviceChangeAvailable('output')) {
        JitsiMeetJS.mediaDevices.enumerateDevices(devices => {
            const audioOutputDevices
                = devices.filter(d => d.kind === 'audiooutput');

            if (audioOutputDevices.length > 1) {
                $('#meet').html(
                    audioOutputDevices
                        .map(
                            d =>
                                `<option value="${d.deviceId}">${d.label}</option>`)
                        .join('\n'));

                $('#meet').show();
            }
        });
    }
</script>
@endsection


