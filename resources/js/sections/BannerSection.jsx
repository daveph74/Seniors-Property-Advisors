import ActionButton from './ActionButton';
import { useHeadingLevel } from './headingLevel';
import useReveal from './useReveal';
import { POSITIONS } from './SectionContainer';
import { overlayOpacityStyle } from './overlayOpacity';

const HEIGHTS = ['compact', 'comfortable', 'tall', 'full'];
const TITLES = ['standard', 'large', 'hero'];
const TEXTS = ['standard', 'large'];
const OVERLAYS = ['none', 'navy', 'navy-strong', 'navy-left', 'navy-bottom', 'white', 'white-strong', 'white-left'];
const WIDTHS = ['narrow', 'standard', 'wide'];

const pick = (list, value, fallback) => (list.includes(value) ? value : fallback);

export default function BannerSection({ data, actions, anchor, editing = false }) {
    const { image = {} } = data;
    const Title = useHeadingLevel() === 1 ? 'h1' : 'h2';
    const height = pick(HEIGHTS, data.height, 'comfortable');
    const title = pick(TITLES, data.titleSize, 'large');
    const text = pick(TEXTS, data.textSize, 'standard');
    const overlay = pick(OVERLAYS, data.overlay, 'navy-left');
    const width = pick(WIDTHS, data.copyWidth, 'standard');
    const position = pick(POSITIONS, data.imagePosition, 'center');
    const light = ! overlay.startsWith('white');
    const reveal = useReveal(data.animation, data.animationDelay, editing);

    const classes = [
        'banner',
        `banner--${height}`,
        `banner--title-${title}`,
        `banner--text-${text}`,
        `banner--copy-${width}`,
        data.align === 'center' ? 'banner--center' : '',
        light ? 'banner--text-light' : 'banner--text-dark',
        reveal.classes,
    ].filter(Boolean).join(' ');

    return (
        <section ref={reveal.ref} className={classes} id={anchor} data-animate={reveal.animation || undefined}>
            <div
                className={`banner__bg banner__bg--pos-${position}`}
                style={image.src ? { backgroundImage: `url('${image.src}')` } : undefined}
                role={image.src ? 'img' : undefined}
                aria-label={image.src ? image.alt || '' : undefined}
            />
            {overlay !== 'none' ? <div className={`section-block__overlay section-block__overlay--${overlay}`} style={overlayOpacityStyle(data)} /> : null}

            <div className="container">
                <div className="banner__copy">
                    {data.eyebrow ? <span className="eyebrow-rule">{data.eyebrow}</span> : null}

                    <Title className="banner__title">
                        {data.heading}
                        {data.headingEm ? <>{data.emOnNewLine ? <br /> : ' '}<em>{data.headingEm}</em></> : null}
                        {data.headingAfter ? <>{data.afterOnNewLine ? <br /> : ' '}{data.headingAfter}</> : null}
                    </Title>

                    {data.lead ? <p className="banner__lead">{data.lead}</p> : null}

                    <div className="hero-ctas">
                        {(data.ctas || []).map((cta, i) => (
                            <ActionButton
                                key={i}
                                cta={cta}
                                actions={actions}
                                className={`btn ${cta.variant || 'primary'}${cta.onNavy && light ? ' on-navy' : ''} lg`}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
