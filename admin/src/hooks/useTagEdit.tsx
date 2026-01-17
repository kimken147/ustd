import { BaseKey, useUpdate } from '@pankod/refine-core';
import { useForm, useModal, Modal, Form, Select } from '@pankod/refine-antd';
import { useState } from 'react';
import { Tag as TagModel } from 'interfaces/tag';
import { useTranslation } from 'react-i18next';

interface UseTagEditProps {
  selectTagProps: any; // Replace with proper type from your useSelector hook
  resource: string;
}

interface UseTagEditReturn {
  tagModal: React.ReactNode;
  showTagModal: (record: { id: BaseKey; tags?: TagModel[] }) => void;
}

export const useTagEdit = ({
  selectTagProps,
  resource,
}: UseTagEditProps): UseTagEditReturn => {
  const { t } = useTranslation();
  const { form: tagModelForm } = useForm();
  const {
    modalProps: tagModalProps,
    show: tagModelShow,
    close: tagModelClose,
  } = useModal();
  const { mutateAsync: updateTags } = useUpdate();
  const [selectedId, setSelectedId] = useState<BaseKey>();

  const showTagModal = (record: { id: BaseKey; tags?: TagModel[] }) => {
    setSelectedId(record.id);
    tagModelForm.setFieldsValue({
      tag_ids: record.tags?.map(tag => tag.id),
    });
    tagModelShow();
  };

  const tagModal = (
    <Modal
      key={selectedId}
      {...tagModalProps}
      onOk={async () => {
        await tagModelForm.validateFields();
        await updateTags({
          id: selectedId!,
          resource,
          values: tagModelForm.getFieldsValue(),
          successNotification: {
            message: t('success'),
            description: t('tagsPage.updateSuccess'),
            type: 'success',
          },
        });
        tagModelClose();
      }}
      destroyOnClose
    >
      <Form form={tagModelForm} key={selectedId}>
        <Form.Item label={t('tagsPage.fields.label')} name={'tag_ids'}>
          <Select
            style={{ width: '80%' }}
            {...selectTagProps}
            mode="multiple"
          />
        </Form.Item>
      </Form>
    </Modal>
  );

  return {
    tagModal,
    showTagModal,
  };
};
