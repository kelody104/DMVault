/**
 * WebRTC P2P 通信管理クラス
 */
class P2PManager {
    constructor(playerId) {
        this.playerId = playerId;
        this.roomId = null;
        this.opponentId = null;
        this.role = null; // 'host' or 'guest'
        this.peerConnection = null;
        this.dataChannel = null;
        this.pollingInterval = null;

        // WebRTC 設定 (Google の無料 STUN サーバーを利用)
        this.config = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };
    }

    /**
     * マッチングを開始する
     */
    async startMatching() {
        try {
            const response = await fetch(`matching.php?action=wait&player_id=${this.playerId}`);
            const result = await response.json();

            if (!result.success) throw new Error(result.error);

            this.roomId = result.room_id;
            this.role = result.role;

            console.log(`Matching started. Room: ${this.roomId}, Role: ${this.role}`);

            if (this.role === 'host') {
                this.waitForGuest();
            } else {
                this.opponentId = 'host'; // 簡易化のためホストのIDを特定
                this.initWebRTC();
            }

            this.startSignalingPolling();
        } catch (error) {
            console.error('Matching failed:', error);
        }
    }

    /**
     * ホストとして参加者を待つ
     */
    waitForGuest() {
        const check = async () => {
            const response = await fetch(`matching.php?action=check&player_id=${this.playerId}&room_id=${this.roomId}`);
            const result = await response.json();

            if (result.status === 'matched') {
                this.opponentId = result.guest_id;
                console.log(`Opponent found: ${this.opponentId}`);
                clearInterval(checkInterval);
                this.initWebRTC();
            }
        };
        const checkInterval = setInterval(check, 2000);
    }

    /**
     * WebRTC の初期化
     */
    async initWebRTC() {
        this.peerConnection = new RTCPeerConnection(this.config);

        // ICE Candidate の発生時
        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignaling('candidate', event.candidate);
            }
        };

        // データチャネルの受信 (Guest側)
        this.peerConnection.ondatachannel = (event) => {
            this.setupDataChannel(event.channel);
        };

        if (this.role === 'host') {
            // ホスト側がデータチャネルを作成
            const channel = this.peerConnection.createDataChannel('gameSync');
            this.setupDataChannel(channel);

            // Offer を作成
            const offer = await this.peerConnection.createOffer();
            await this.peerConnection.setLocalDescription(offer);
            this.sendSignaling('offer', offer);
        }
    }

    /**
     * データチャネルのセットアップ
     */
    setupDataChannel(channel) {
        this.dataChannel = channel;
        this.dataChannel.onopen = () => console.log('DataChannel opened');
        this.dataChannel.onmessage = (event) => this.onMessage(JSON.parse(event.data));
        this.dataChannel.onclose = () => console.log('DataChannel closed');
    }

    /**
     * シグナリングデータの送信
     */
    async sendSignaling(type, data) {
        await fetch('signaling.php', {
            method: 'POST',
            body: JSON.stringify({
                room_id: this.roomId,
                from_id: this.playerId,
                to_id: this.opponentId,
                type: type,
                data: data
            })
        });
    }

    /**
     * シグナリングのポーリング開始
     */
    startSignalingPolling() {
        this.pollingInterval = setInterval(async () => {
            const response = await fetch(`signaling.php?player_id=${this.playerId}`);
            const result = await response.json();

            if (result.success && result.messages) {
                for (const msg of result.messages) {
                    this.handleSignalingMessage(msg);
                }
            }
        }, 2000);
    }

    /**
     * 受信したシグナリングデータの処理
     */
    async handleSignalingMessage(msg) {
        const data = JSON.parse(msg.data);

        switch (msg.type) {
            case 'offer':
                await this.peerConnection.setRemoteDescription(new RTCSessionDescription(data));
                const answer = await this.peerConnection.createAnswer();
                await this.peerConnection.setLocalDescription(answer);
                this.sendSignaling('answer', answer);
                break;
            case 'answer':
                await this.peerConnection.setRemoteDescription(new RTCSessionDescription(data));
                break;
            case 'candidate':
                await this.peerConnection.addIceCandidate(new RTCIceCandidate(data));
                break;
        }
    }

    /**
     * データの送信
     */
    send(type, payload) {
        if (this.dataChannel && this.dataChannel.readyState === 'open') {
            this.dataChannel.send(JSON.stringify({ type, payload }));
        }
    }

    /**
     * データ受信時の処理
     */
    onMessage(data) {
        console.log('Message received:', data);
        // ここでゲームアクションに応じた処理を行う
    }
}
