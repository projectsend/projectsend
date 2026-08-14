import { KeyRound } from 'lucide-react';
import { useState } from 'react';

import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

export interface TokenAbility {
    key: string;
    label: string;
    category: string;
    /** False when the owner has since lost the permission — carried, but ignored on every request. */
    effective: boolean;
}

export interface UserApiToken {
    id: string;
    name: string;
    active: boolean;
    created_at: string | null;
    last_used_at: string | null;
    expires_at: string | null;
    abilities: TokenAbility[];
}

/**
 * What API credentials a staff account holds, on the API tab of its edit
 * screen.
 *
 * Read-only by design, matching the server: an administrator can see that
 * an integration exists and what it is allowed to do — their
 * installation's security posture — but renaming, re-scoping and revoking
 * a token stay with its owner, on /settings/api-tokens. No secret is
 * shown here or anywhere else; only a hash is ever stored.
 *
 * Abilities live behind a per-token dialog rather than in the row. A
 * token routinely carries a dozen of them, and spilling that into a table
 * cell turns a list of four integrations into a wall of text you have to
 * read past to find the one you came for.
 */
export function ApiTokensPanel({ tokens, userName }: { tokens: UserApiToken[]; userName: string }) {
    const { t } = useTranslation();
    const { date } = useFormatDate();
    const [inspecting, setInspecting] = useState<UserApiToken | null>(null);

    const active = tokens.filter((token) => token.active).length;

    return (
        <div className="space-y-6">
            <div className="flex gap-8">
                <div>
                    <p className="text-2xl font-semibold">{tokens.length}</p>
                    <p className="text-muted-foreground text-sm">{t('Tokens created')}</p>
                </div>
                <div>
                    <p className="text-2xl font-semibold">{active}</p>
                    <p className="text-muted-foreground text-sm">{t('Currently active')}</p>
                </div>
            </div>

            <TableShell
                columns={[t('Name'), t('Created'), t('Last used'), t('Expires'), t('Status'), null]}
                isEmpty={tokens.length === 0}
                emptyMessage={<>{t('This account has never created an API token.')}</>}
            >
                {tokens.map((token) => (
                    <tr key={token.id} className="border-b last:border-0">
                        <td className="px-4 py-2.5 font-medium">{token.name}</td>
                        <td className="text-muted-foreground px-4 py-2.5">{date(token.created_at) || '—'}</td>
                        <td className="text-muted-foreground px-4 py-2.5">{token.last_used_at === null ? t('Never') : date(token.last_used_at)}</td>
                        <td className="text-muted-foreground px-4 py-2.5">{token.expires_at === null ? t('Never') : date(token.expires_at)}</td>
                        <td className="px-4 py-2.5">
                            <Badge variant={token.active ? 'secondary' : 'destructive'}>{token.active ? t('Active') : t('Expired')}</Badge>
                        </td>
                        <td className="px-4 py-2.5 text-right">
                            <Button variant="outline" size="sm" onClick={() => setInspecting(token)}>
                                {/* Singular spelled out rather than left as
                                    ":count permissions" with a 1 in it — this is
                                    a button label, read far more closely than the
                                    row counts elsewhere in the app. */}
                                {token.abilities.length === 1 ? t('1 permission') : t(':count permissions', { count: token.abilities.length })}
                            </Button>
                        </td>
                    </tr>
                ))}
            </TableShell>

            <p className="text-muted-foreground text-sm">
                {t('Only :name can create, change or revoke these — an administrator can see them, but not manage them.', {
                    name: userName,
                })}
            </p>

            {/* One dialog reused for whichever row was clicked, rather than one
                mounted per row: a hundred hidden dialogs is a hundred portals. */}
            <Dialog open={inspecting !== null} onOpenChange={(open) => !open && setInspecting(null)}>
                <DialogContent className="max-h-[80vh] overflow-y-auto">
                    <DialogTitle>{inspecting?.name}</DialogTitle>
                    <DialogDescription>
                        {t('What this token is allowed to do through the API. It can never do more than :name may.', {
                            name: userName,
                        })}
                    </DialogDescription>

                    {inspecting !== null && inspecting.abilities.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('This token carries no permissions.')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {inspecting?.abilities.map((ability) => (
                                <li key={ability.key} className="flex items-start gap-2 text-sm">
                                    <KeyRound className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                    <span>
                                        <span className={ability.effective ? '' : 'text-muted-foreground line-through'}>{t(ability.label)}</span>
                                        {ability.category !== '' && <span className="text-muted-foreground"> · {t(ability.category)}</span>}
                                        {!ability.effective && (
                                            <span className="text-muted-foreground block text-xs">
                                                {t('No longer granted to this account, so it does nothing.')}
                                            </span>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
