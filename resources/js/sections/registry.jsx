import HeroSection from './HeroSection';
import TrustCardsSection from './TrustCardsSection';
import ProcessStepsSection from './ProcessStepsSection';
import WhyListSection from './WhyListSection';
import AgentCompareSection from './AgentCompareSection';
import FamilySection from './FamilySection';
import CtaSection from './CtaSection';

export const SECTIONS = {
    hero: HeroSection,
    'trust-cards': TrustCardsSection,
    'process-steps': ProcessStepsSection,
    'why-list': WhyListSection,
    'agent-compare': AgentCompareSection,
    family: FamilySection,
    cta: CtaSection,
};

export const SECTION_LABELS = {
    hero: 'Hero banner',
    'trust-cards': 'Trust cards',
    'process-steps': 'Process steps',
    'why-list': 'Why list',
    'agent-compare': 'Agent comparison',
    family: 'For families',
    cta: 'Call to action',
};

export function resolveSection(type) {
    return SECTIONS[type] || null;
}
