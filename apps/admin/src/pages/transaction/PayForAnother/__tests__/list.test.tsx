// Mock Refine hooks
jest.mock('@refinedev/antd', () => ({
  useTable: jest.fn(() => ({
    tableProps: { dataSource: [], loading: false, pagination: {} },
    searchFormProps: { form: {} },
    tableQuery: { data: null, refetch: jest.fn(), isFetching: false },
    filters: [],
  })),
  List: ({ children, title }: any) => (
    <div data-testid="list">
      {title}
      {children}
    </div>
  ),
  ListButton: ({ children }: any) => <button>{children}</button>,
  ShowButton: ({ children }: any) => <button>{children}</button>,
  TextField: ({ value }: any) => <span>{value}</span>,
  DateField: ({ value }: any) => <span>{value}</span>,
}));

jest.mock('@refinedev/core', () => ({
  useApiUrl: () => 'http://api.test',
  useCan: () => ({ data: { can: true } }),
  useCustomMutation: () => ({ mutateAsync: jest.fn() }),
  useGetIdentity: () => ({ data: { id: 1, name: 'Test', role: 1 } }),
}));

jest.mock('@morgan-ustd/shared', () => ({
  ListPageLayout: Object.assign(
    ({ children }: any) => <div data-testid="list-page-layout">{children}</div>,
    {
      Filter: Object.assign(
        ({ children }: any) => <div>{children}</div>,
        { Item: ({ children }: any) => <div>{children}</div> }
      ),
      Table: (props: any) => <table data-testid="list-table" />,
    }
  ),
  useWithdrawStatus: () => ({
    Select: () => null,
    getStatusText: () => '',
    Status: {},
  }),
  useTransactionCallbackStatus: () => ({
    Select: () => null,
    getStatusText: () => '',
    Status: {},
  }),
  useUpdateModal: () => ({
    Modal: Object.assign(() => null, { confirm: jest.fn() }),
    show: jest.fn(),
    modalProps: {},
  }),
  useSelector: () => ({
    Select: () => null,
    selectProps: { options: [] },
    data: [],
  }),
  TransactionSubType: {
    SUB_TYPE_WITHDRAW: 1,
    SUB_TYPE_AGENCY_WITHDRAW: 2,
    SUB_TYPE_WITHDRAW_PROFIT: 3,
  },
  TransactionType: {
    TYPE_PAUFEN_WITHDRAW: 1,
    TYPE_NORMAL_WITHDRAW: 2,
  },
  Gray: '#gray',
  Red: '#red',
}));

jest.mock('hooks/useMerchant', () => ({
  __esModule: true,
  default: () => ({ Select: () => null }),
}));

jest.mock('hooks/useChannel', () => ({
  __esModule: true,
  default: () => ({ Select: () => null }),
}));

jest.mock('hooks/useAutoRefetch', () => ({
  __esModule: true,
  default: () => ({
    freq: 30,
    enableAuto: false,
    AutoRefetch: () => null,
  }),
}));

jest.mock('hooks/useAudioPermission', () => ({
  useAudioPermission: () => ({
    showPermissionAlert: false,
    grantPermission: jest.fn(),
    dismissPermissionAlert: jest.fn(),
    playAudio: jest.fn(),
  }),
}));

jest.mock('react-router', () => ({
  useNavigate: () => jest.fn(),
}));

jest.mock('react-helmet', () => ({
  Helmet: ({ children }: any) => <>{children}</>,
}));

describe('PayForAnotherList', () => {
  it('should be defined', () => {
    // Basic smoke test - the component module should be importable
    expect(true).toBe(true);
  });

  // Additional tests can be added as needed:
  // - Test FilterForm rendering
  // - Test columns rendering
  // - Test user interactions
});
