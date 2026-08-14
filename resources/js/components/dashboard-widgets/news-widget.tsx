import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

export interface NewsItem {
    title: string;
    date: string;
    content: string;
    link: string;
}

export function NewsWidget({ news }: { news: NewsItem[] }) {
    const { t } = useTranslation();
    const { calendarDateShort } = useFormatDate();

    return (
        <div>
            {news.length === 0 ? (
                <p className="text-muted-foreground text-sm">{t('No news yet.')}</p>
            ) : (
                <div className="space-y-4">
                    {news.map((item) => (
                        <div key={item.link} className="space-y-1">
                            <div className="flex items-baseline justify-between gap-2">
                                <p className="text-sm font-medium">{item.title}</p>
                                <span className="text-muted-foreground shrink-0 text-xs">{calendarDateShort(item.date)}</span>
                            </div>
                            <div
                                className="text-muted-foreground line-clamp-2 text-sm [&_a]:hidden [&_br]:hidden"
                                dangerouslySetInnerHTML={{ __html: item.content }}
                            />
                            <a href={item.link} target="_blank" rel="noreferrer" className="text-xs underline hover:no-underline">
                                {t('Read more')}
                            </a>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
