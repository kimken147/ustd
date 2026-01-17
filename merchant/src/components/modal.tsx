import { ModalProps, Modal as AntdModal, ModalFuncProps } from "@pankod/refine-antd";

function Modal(props: ModalProps) {
    return <AntdModal {...props} />;
}

Modal.confirm = (props: ModalFuncProps) => {
    AntdModal.confirm({
        okText: "确定",
        cancelText: "取消",
        ...props,
    });
};

export default Modal;
