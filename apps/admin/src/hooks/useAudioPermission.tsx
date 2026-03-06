import { useState, useRef, useCallback, useEffect } from "react";

const AUDIO_PERMISSION_KEY = "audio_permission_granted";

// 自定義 Hook 處理音訊播放權限
export const useAudioPermission = (audioSrc: string) => {
    const [permissionGranted, setPermissionGranted] = useState(() => {
        return localStorage.getItem(AUDIO_PERMISSION_KEY) === "true";
    });
    const [showPermissionAlert, setShowPermissionAlert] = useState(false);
    const audioRef = useRef<HTMLAudioElement | null>(null);
    const audioCtxRef = useRef<AudioContext | null>(null);

    // 懶初始化 Audio 元素
    const getAudio = useCallback(() => {
        if (!audioRef.current) {
            audioRef.current = new Audio(audioSrc);
        }
        return audioRef.current;
    }, [audioSrc]);

    // 頁面載入時，如果之前已授權，嘗試透過用戶互動解鎖 AudioContext
    useEffect(() => {
        if (!permissionGranted) {
            setShowPermissionAlert(true);
            return;
        }

        // 已授權過，監聽任意用戶互動來解鎖 AudioContext
        const unlockAudio = () => {
            const audio = getAudio();
            // 用靜音播放解鎖
            audio.volume = 0;
            audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
                audio.volume = 1;
            }).catch(() => {
                // 如果仍然失敗，需要重新請求權限
            });

            if (audioCtxRef.current?.state === "suspended") {
                audioCtxRef.current.resume();
            }

            document.removeEventListener("click", unlockAudio);
            document.removeEventListener("keydown", unlockAudio);
        };

        document.addEventListener("click", unlockAudio, { once: true });
        document.addEventListener("keydown", unlockAudio, { once: true });

        return () => {
            document.removeEventListener("click", unlockAudio);
            document.removeEventListener("keydown", unlockAudio);
        };
    }, [permissionGranted, getAudio]);

    // 授予許可 — 在 button click handler 中呼叫，算作用戶手勢
    const grantPermission = useCallback(async () => {
        try {
            const audio = getAudio();
            audio.volume = 0;
            await audio.play();
            audio.pause();
            audio.currentTime = 0;
            audio.volume = 1;

            // 同時解鎖 AudioContext
            if (!audioCtxRef.current) {
                audioCtxRef.current = new AudioContext();
            }
            if (audioCtxRef.current.state === "suspended") {
                await audioCtxRef.current.resume();
            }

            setPermissionGranted(true);
            localStorage.setItem(AUDIO_PERMISSION_KEY, "true");
            return true;
        } catch (error) {
            console.error("無法獲取音訊播放許可:", error);
            return false;
        } finally {
            setShowPermissionAlert(false);
        }
    }, [getAudio]);

    // 關閉許可對話框
    const dismissPermissionAlert = useCallback(() => {
        setShowPermissionAlert(false);
        localStorage.setItem(AUDIO_PERMISSION_KEY, "false");
    }, []);

    // 實際播放音訊的函數
    const playAudio = useCallback(async () => {
        if (!permissionGranted) {
            setShowPermissionAlert(true);
            return false;
        }

        try {
            const audio = getAudio();
            audio.volume = 1;
            audio.currentTime = 0;
            await audio.play();
            return true;
        } catch (error) {
            console.error("播放音訊失敗:", error);
            // 播放失敗不再彈窗，等下次用戶互動後自動解鎖
            return false;
        }
    }, [permissionGranted, getAudio]);

    return {
        permissionGranted,
        showPermissionAlert,
        grantPermission,
        dismissPermissionAlert,
        playAudio,
    };
};
