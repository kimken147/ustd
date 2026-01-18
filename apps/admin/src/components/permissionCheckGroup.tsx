import { Divider, Row, Col, Checkbox } from 'antd';
import type { CheckboxChangeEvent } from 'antd/es/checkbox';
import usePermission from 'hooks/usePermission';
import { Permission } from 'interfaces/subAccount';
import Enviroment from 'lib/env';
import { cloneDeep } from 'lodash';
import { FC, useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

type Props = {
  defaultIds?: number[];
  onChange?: (ids: number[]) => void;
};

const PermissionCheckGroup: FC<Props> = ({ defaultIds = [], onChange }) => {
  const isPaufen = Enviroment.isPaufen;
  const { t } = useTranslation();
  const groupName = isPaufen
    ? t('common.providerManagement')
    : t('common.groupManagement');
  const [selectedIds, setSelectedIds] = useState<number[]>(defaultIds);
  const { permissions } = usePermission();
  const groups =
    permissions?.reduce<Record<string, Permission[]>>((prev, cur) => {
      const clonCur = cloneDeep(cur);
      // 使用翻譯後的 group_name，不需要硬編碼比較
      if (clonCur.id === 26 || clonCur.id === 27) {
        const targetGroupName = isPaufen
          ? t('common.providerManagement')
          : t('common.groupManagement');
        if (!prev[targetGroupName]) prev[targetGroupName] = [];
        prev[targetGroupName].push(clonCur);
        return prev;
      }
      if (clonCur.id === 28) {
        const targetGroupName = t('common.merchantManagement');
        if (!prev[targetGroupName]) prev[targetGroupName] = [];
        prev[targetGroupName].push(clonCur);
        return prev;
      }
      if (clonCur.id === 33) {
        const targetGroupName = t('common.financeReport');
        if (!prev[targetGroupName]) prev[targetGroupName] = [];
        prev[targetGroupName].push({
          ...clonCur,
          group_name: targetGroupName,
        });
        return prev;
      }
      // 直接使用 API 返回的翻譯後的 group_name
      if (!prev[clonCur.group_name]) prev[clonCur.group_name] = [];
      prev[clonCur.group_name].push(clonCur);
      return prev;
    }, {}) ?? {};
  return (
    <>
      {Object.entries(groups).map(([key, value], index) =>
        value?.length ? (
          <div key={key}>
            {index > 0 ? <Divider /> : null}
            <Row key={key} gutter={4} className="w-full">
              <Col span={7}>{key}:</Col>
              <Col span={17}>
                <Row className="w-full">
                  {value.map(per => (
                    <Col span={12} key={per.id}>
                      <Checkbox
                        defaultChecked={selectedIds.includes(per.id)}
                        onChange={e => {
                          const checked = e.target.checked;
                          const ids = checked
                            ? [...selectedIds, per.id]
                            : selectedIds.filter(id => id !== per.id);
                          setSelectedIds(ids);
                          onChange?.(ids);
                        }}
                      >
                        {per.name}
                      </Checkbox>
                    </Col>
                  ))}
                </Row>
              </Col>
            </Row>
          </div>
        ) : null
      )}
    </>
  );
};

export default PermissionCheckGroup;
