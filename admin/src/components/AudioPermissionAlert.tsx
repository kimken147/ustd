type Props = {
    onConfirm?: () => void;
    onDismiss?: () => void;
};

const AudioPermissionAlert = ({ onConfirm, onDismiss }: Props) => {
    return (
        <div className="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50">
            <div className="bg-white p-6 rounded-lg shadow-lg max-w-md">
                <h3 className="text-lg font-bold mb-2">启用声音通知</h3>
                <p className="mb-4">系统需要您的许可来播放新提款通知音效。要启用声音通知吗？</p>
                <div className="flex justify-end space-x-2">
                    <button className="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300" onClick={onDismiss}>
                        不需要
                    </button>
                    <button className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" onClick={onConfirm}>
                        允许通知
                    </button>
                </div>
            </div>
        </div>
    );
};

export default AudioPermissionAlert;
