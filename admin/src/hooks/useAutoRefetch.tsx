import { Space, Switch, Typography } from '@pankod/refine-antd';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

type Props = {
  defaultFreq?: number;
};

function useAutoRefetch(props?: Props) {
  const { t } = useTranslation();
  const [freq, setFreq] = useState<number>(props?.defaultFreq || 10);
  const [enableAuto, setEnableAuto] = useState(false);
  const [editing, setEditing] = useState(false);

  const AutoRefetch = () => (
    <Space align="center" className="px-4 mb-4">
      {t('autoRefresh')}
      {'('}
      <Typography.Text
        editable={{
          editing,
          onChange(value) {
            if (!Number.isInteger(+value)) return;
            else setFreq(+value);
          },
          onStart() {
            setEnableAuto(false);
            setEditing(true);
          },
          onEnd() {
            setEnableAuto(true);
            setEditing(false);
          },
        }}
      >
        {freq.toString()}
      </Typography.Text>
      {')'}
      <Switch checked={enableAuto} onChange={check => setEnableAuto(check)} />
    </Space>
  );

  return {
    AutoRefetch,
    freq,
    enableAuto,
  };
}

export default useAutoRefetch;
