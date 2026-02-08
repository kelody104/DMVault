/**
 * WebRTC P2P 通信管理クラス (SkyWay SDK Core 版)
 */
class P2PManager {
    constructor(appId, token) {
        this.appId = appId; // SkyWay App ID
        this.token = token; // SkyWay Auth Token (本番ではサーバーで生成)
        this.context = null;
        this.room = null;
        this.localDataStream = null;
        this.dataConnections = new Set(); // 接続中のメンバーのデータストリーム

        // コールバック関数
        this.onConnected = () => { };
        this.onDisconnected = () => { };
        this.onMessageReceived = (data) => { };
    }

    /**
     * SkyWay の初期化とルーム参加
     * @param {string} roomName 
     * @param {string} memberName 
     */
    async joinRoom(roomName, memberName) {
        try {
            // Context の作成
            this.context = await SkyWayContext.Create(this.token);

            // ルームの取得または作成 (P2P 形式)
            this.room = await SkyWayRoom.FindOrCreate(this.context, {
                type: 'p2p',
                name: roomName
            });

            // ルームに参加
            const member = await this.room.join({ name: memberName });
            console.log(`Joined room: ${roomName} as ${memberName}`);

            // 自分のデータストリームを作成してパブリッシュ
            this.localDataStream = await SkyWayStreamFactory.createDataStream();
            await member.publish(this.localDataStream);

            // すでにルームにいる、または後から来るメンバーのストリームを処理
            this.room.onPublicationExposed.add(({ publication }) => {
                if (publication.publisher.id !== member.id && publication.contentType === 'data') {
                    this.subscribeStream(member, publication);
                }
            });

            // 既存のパブリケーションをチェック
            for (const publication of this.room.publications) {
                if (publication.publisher.id !== member.id && publication.contentType === 'data') {
                    this.subscribeStream(member, publication);
                }
            }

            this.onConnected();
        } catch (error) {
            console.error('SkyWay connection failed:', error);
            throw error;
        }
    }

    /**
     * ストリームの購読（サブスクライブ）
     */
    async subscribeStream(selfMember, publication) {
        const { stream } = await selfMember.subscribe(publication.id);
        console.log(`Subscribed to: ${publication.publisher.id}`);

        this.dataConnections.add(stream);

        // データ受信時の処理
        stream.onData.add((data) => {
            this.onMessageReceived(data);
        });
    }

    /**
     * データの送信 (全メンバーへ)
     */
    send(type, payload) {
        if (this.localDataStream) {
            const data = JSON.stringify({ type, payload });
            this.localDataStream.write(data);
            console.log('Message sent:', data);
        }
    }

    /**
     * 切断
     */
    async leave() {
        if (this.room) {
            await this.room.leave();
            this.room = null;
            this.onDisconnected();
        }
    }
}
