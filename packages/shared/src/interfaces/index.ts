// Antd types
export * from './antd';

// Bank types
export * from './bank';

// Channel types - primary source for channel-related interfaces
export {
    Channel,
    ChannelGroup,
    DepositAccountFields,
    Fields,
} from './channel';

// ChannelAmounts types (use ChannelAmount* prefix to avoid conflicts)
export { ChannelAmount } from './channelAmounts';
export type { DepositAccountFields as ChannelAmountDepositAccountFields } from './channelAmounts';
export type { Fields as ChannelAmountFields } from './channelAmounts';

// ChannelGroup types (already exported from channel.ts, skip duplicate)
// export * from './channelGroup';

// Tag types
export * from './tag';

// Merchant types
export {
    Provider as MerchantProvider,
    Merchant,
    UserChannel as MerchantUserChannel,
    DepositAccountFields as MerchantDepositAccountFields,
    Fields as MerchantFields,
    Meta as MerchantMeta,
    Wallet as MerchantWallet,
    Agent as MerchantAgent,
    Links as MerchantLinks,
} from './merchant';

// MerchantWallet types (wallet history)
export {
    MerchantWallet as MerchantWalletHistory,
    User as MerchantWalletUser,
    Operator as MerchantWalletOperator,
    Links as MerchantWalletLinks,
    Meta as MerchantWalletMeta,
} from './merchantWallet';

// Transaction types
export {
    ITransactionRes,
    Transaction,
    Merchant as TransactionMerchant,
    Provider as TransactionProvider,
    MerchantFee,
    Merchant2,
    Stat as TransactionStat,
    Links as TransactionLinks,
    Meta as TransactionMeta,
    DemoCreateRes,
    Thirdchannel,
    FromChannelAccount,
    ToChannelAccount,
    TransactionType,
    TransactionSubType,
} from './transaction';

// User types
export {
    User,
    WhitelistedIp,
    UserChannel,
    DepositAccountFields as UserDepositAccountFields,
    Fields as UserFields,
} from './user';

// UserChannel types (provider user channel)
export {
    BaseRecord,
    Agent,
    UserOfChannel,
    Detail,
    Device,
    UserChannel as ProviderUserChannel,
    Links,
    Meta,
    IUserChannelRes,
    UserChannelType,
    UserChannelStatus,
    Channel as UserChannelChannel,
    ChannelGroup as UserChannelGroup,
    DepositAccountFields as UserChannelDepositAccountFields,
    Fields as UserChannelFields,
    IUserChannelQuery,
} from './userChannel';

// Withdraw types
export {
    Withdraw,
    Provider as WithdrawProvider,
    User as WithdrawUser,
    MerchantFee as WithdrawMerchantFee,
    Merchant as WithdrawMerchant,
    AllRelativeWithdraw,
    Thirdchannel as WithdrawThirdchannel,
    Note as WithdrawNote,
    Thirdchannel2,
    LockedBy2,
    Links as WithdrawLinks,
    Meta as WithdrawMeta,
    TransactionNote,
    ToChannelAccount as WithdrawToChannelAccount,
    Detail as WithdrawDetail,
    Device as WithdrawDevice,
    ProviderFee,
    Provider2,
    CertificateFile,
} from './withdraw';
