import { useState, useRef } from "react";

// 自定義 Hook 處理音訊播放權限
export const useAudioPermission = (audioSrc: string) => {
    const [permissionGranted, setPermissionGranted] = useState(false);
    const [showPermissionAlert, setShowPermissionAlert] = useState(false);
    const audioRef = useRef(new Audio(audioSrc));

    // 測試音訊播放權限
    const testAutoplay = async () => {
        try {
            // 嘗試播放音訊 (靜音)
            await audioRef.current.play();
            audioRef.current.pause();
            audioRef.current.currentTime = 0;
            setPermissionGranted(true);
            return true;
        } catch (error) {
            console.log("需要用戶許可來播放音訊:", error);
            setShowPermissionAlert(true);
            return false;
        }
    };

    // 授予許可
    const grantPermission = async () => {
        try {
            await audioRef.current.play();
            audioRef.current.currentTime = 0;
            setPermissionGranted(true);
            return true;
        } catch (error) {
            console.error("無法獲取音訊播放許可:", error);
            return false;
        } finally {
            setShowPermissionAlert(false);
        }
    };

    // 關閉許可對話框
    const dismissPermissionAlert = () => {
        setShowPermissionAlert(false);
    };

    // 實際播放音訊的函數
    const playAudio = async () => {
        if (!permissionGranted) {
            const granted = await testAutoplay();
            if (!granted) return false;
        }

        try {
            audioRef.current.volume = 1; // 恢復音量
            audioRef.current.currentTime = 0;
            await audioRef.current.play();
            return true;
        } catch (error) {
            console.error("播放音訊失敗:", error);
            setShowPermissionAlert(true);
            return false;
        }
    };

    return {
        permissionGranted,
        showPermissionAlert,
        testAutoplay,
        grantPermission,
        dismissPermissionAlert,
        playAudio,
    };
};
