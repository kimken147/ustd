import { InternalTransfer } from "interfaces/internalTransfer";
import { Withdraw } from "@morgan-ustd/shared";

export const getReceiptUrl = (record: Withdraw | InternalTransfer) => {
    return `${process.env.REACT_APP_HOST}/v1/receipt/${record.system_order_number}`;
};

const tronNetwork = process.env.REACT_APP_TRON_NETWORK || 'mainnet';
const tronPrefix = tronNetwork === 'mainnet' ? '' : `${tronNetwork}.`;

const EXPLORER_MAP: Record<string, string> = {
    trc20: `https://${tronPrefix}tronscan.org/#/transaction/`,
    'trc-20': `https://${tronPrefix}tronscan.org/#/transaction/`,
    erc20: 'https://etherscan.io/tx/',
    'erc-20': 'https://etherscan.io/tx/',
    bsc20: 'https://bscscan.com/tx/',
    'bsc-20': 'https://bscscan.com/tx/',
};

export const getExplorerTxUrl = (txHash: string, chainNetwork?: string): string | null => {
    if (!txHash || !chainNetwork) return null;
    const base = EXPLORER_MAP[chainNetwork.toLowerCase()];
    return base ? `${base}${txHash}` : null;
};
