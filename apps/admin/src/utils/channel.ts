export const USDT_CODES = ['USDT_TRC20', 'USDT_ERC20', 'USDT_BEP20'];
export const isUsdtChannel = (code?: string): boolean =>
  !!code && USDT_CODES.includes(code);
