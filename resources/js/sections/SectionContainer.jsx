export default function SectionContainer({ data = {}, anchor, children }) {
    return (
        <section className="section-block" id={anchor}>
            <div className={`section-block__inner section-block__inner--${data.width || 'standard'}`}>
                {children}
            </div>
        </section>
    );
}
