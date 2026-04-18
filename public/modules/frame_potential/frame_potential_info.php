<?php
declare(strict_types=1);

/**
 * Renders the Frame Potential article modal and its supporting CSS/JS.
 *
 * Usage:
 *   1) Call renderFramePotentialArticleModal(); somewhere near the bottom of your page.
 *   2) Add a button or link with id="fp-open-modal" (or custom, see JS below) to trigger it.
 */

function renderFramePotentialArticleModal(): void
{
    static $included = false;
    if ($included) {
        return;
    }
    $included = true;
    ?>
    <!-- Frame Potential Article Modal -->
    <div id="fp-article-modal" class="fp-modal" aria-hidden="true">
        <div class="fp-modal-backdrop"></div>
        <div class="fp-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="fp-modal-title">
            <button type="button" class="fp-modal-close" aria-label="Close Frame Potential article">&times;</button>

            <header class="fp-modal-header">
                <h2 id="fp-modal-title">Frame Potential: The Biomechanical Limits of Muscle and Strength Expression</h2>
                <p class="fp-modal-subtitle">
                    A scientific overview of how your skeletal structure shapes the upper boundary of your long-term strength
                    and hypertrophy potential.
                </p>
            </header>

            <div class="fp-modal-body">
                <h3 class="fp-article-heading">Abstract</h3>
                <p>
                    Frame Potential is a construct describing the upper boundary of muscular and strength development
                    determined primarily by skeletal architecture. It does not prescribe destiny or guarantee outcomes; rather,
                    it defines the structural envelope within which adaptation can occur. By combining anthropometric data
                    with biomechanical and resistance-training research, exFIT uses Frame Potential to provide a realistic
                    long-term roadmap for strength and hypertrophy.
                </p>

                <h3 class="fp-article-heading">Introduction</h3>
                <p>
                    Lifters often compare progress without recognizing that strength and muscularity are constrained by
                    structural factors. Genetics, hormones, nutrition, and recovery all influence adaptation, but beneath these
                    variables lies the skeletal frame. Frame Potential describes the maximum theoretical zone of muscular
                    development an individual can express under optimal behavioral conditions. Understanding this concept
                    helps explain why two people on identical programs can experience very different long-term outcomes.
                </p>

                <h3 class="fp-article-heading">Skeletal Geometry as a Limiting Factor</h3>
                <p>
                    Bone breadth, especially at the wrist and ankle, is strongly correlated with lean mass potential.
                    Thicker bones provide more surface area for tendon attachment and more robust levers for transmitting
                    load. Research shows that wrist circumference is associated with upper-body bone mass and fat-free mass,
                    while ankle circumference relates to lower-body robustness and load-bearing capacity. Clavicle width
                    influences shoulder breadth and deltoid hypertrophy potential, effectively shaping the “scaffold” upon
                    which muscle can develop.
                </p>

                <h3 class="fp-article-heading">Lever Arms and Biomechanical Efficiency</h3>
                <p>
                    Limb lengths determine mechanical advantage or disadvantage in common lifts. Long femurs can increase
                    forward torque demands in squats, making balance and hip contribution more challenging. Shorter arms may
                    confer advantages in pressing or deadlifting due to reduced range of motion and altered moment arms.
                    Frame Potential incorporates these realities by accounting not only for how much mass a frame can hold,
                    but also how efficiently that mass can express force in specific movement patterns.
                </p>

                <h3 class="fp-article-heading">Genetic Anchoring of Frame Dimensions</h3>
                <p>
                    In adulthood, skeletal proportions are largely fixed. While bone mineral density can adapt to loading,
                    the macro-geometry—lengths, joint positions, and overall architecture—does not meaningfully change.
                    Work on bone functional adaptation supports this: bone responds to mechanical stress by adjusting
                    density and microarchitecture, not lever lengths. This makes Frame Potential a stable boundary condition
                    while muscle and neuromuscular adaptations remain flexible within that boundary.
                </p>

                <h3 class="fp-article-heading">Hypertrophy Mechanisms Within Frame Limits</h3>
                <p>
                    Muscle hypertrophy is driven mainly by mechanical tension, muscle damage, and metabolic stress. These
                    mechanisms operate within the constraints of the frame: tendon insertion points, bone length, and joint
                    torque tolerances define how effectively stimuli can be applied and tolerated. Two lifters can train
                    identically, yet one may develop broader shoulders, thicker thighs, or superior leverage in certain lifts
                    simply because their skeleton provides more advantageous attachment sites or lever arms.
                </p>

                <h3 class="fp-article-heading">Inter-Individual Variability in Adaptation</h3>
                <p>
                    Controlled resistance-training studies consistently report large variability in strength and size gains.
                    Some individuals show minimal hypertrophy, while others achieve substantial increases under the same
                    protocol. Frame Potential offers one explanatory lens: individuals with more robust or mechanically
                    favorable frames possess a larger envelope for adaptation, even if short-term responses vary due to
                    nutrition, sleep, or programming differences.
                </p>

                <h3 class="fp-article-heading">How exFIT Uses Frame Potential</h3>
                <p>
                    exFIT operationalizes Frame Potential by combining user-entered anthropometry—height, wrist and ankle
                    circumference, sex, clavicle width, and limb ratios—with evidence-based models of lean mass and strength
                    capacity. Instead of outputting a single prediction, the system generates a range of plausible outcomes
                    over time. This informs long-term planning, including realistic strength targets, expected progression
                    rates, and the upper boundary of lean mass that is sustainable without pharmacological enhancement.
                </p>

                <h3 class="fp-article-heading">Psychological &amp; Behavioral Implications</h3>
                <p>
                    Framing goals in terms of Frame Potential has psychological advantages. Rather than chasing another
                    person’s genetics or social-media physique, exFIT users are invited to optimize their own structure.
                    Progress is measured not against external physiques, but against an individualized biomechanical ceiling.
                    This perspective supports adherence, reduces ego-driven comparison, and encourages a long-term, health-
                    aligned training mindset.
                </p>

                <h3 class="fp-article-heading">Conclusion</h3>
                <p>
                    Frame Potential provides a biomechanically grounded way to understand what the body could achieve under
                    ideal behavioral conditions. While it cannot guarantee results, it contextualizes them by acknowledging
                    the structural constraints imposed by the skeleton. By integrating this concept into program design,
                    exFIT offers users a more rational, personalized roadmap for long-term strength and hypertrophy
                    development. The frame defines the arena; daily practice determines how close an individual can come to
                    fully expressing that potential.
                </p>

                <h3 class="fp-article-heading">Key References (APA)</h3>
                <ul class="fp-ref-list">
                    <li>
                        Hubal, M. J., Gordish-Dressman, H., Thompson, P. D., Price, T. B., Hoffman, E. P.,
                        Angelopoulos, T. J., … Clarkson, P. M. (2005).
                        Variability in muscle size and strength gain after unilateral resistance training.
                        <em>Medicine &amp; Science in Sports &amp; Exercise, 37</em>(6), 964–972.
                    </li>
                    <li>
                        Kjeldsen, M. H., Slimani, M., Clark, C., Øverby, N. C., &amp; Haugvad, L. (2020).
                        Correlations between anthropometric measures, bone structure, and maximal strength performance.
                        <em>Journal of Strength and Conditioning Research, 34</em>(9), 2561–2571.
                    </li>
                    <li>
                        Morton, R. W., Colenso-Semple, L., &amp; Phillips, S. M. (2018).
                        Training for strength and hypertrophy: An evidence-based approach.
                        <em>Current Opinion in Physiology, 10</em>, 90–95.
                    </li>
                    <li>
                        Ruff, C. B., Holt, B., &amp; Trinkaus, E. (2018).
                        Who’s afraid of the big bad Wolff?: “Wolff's Law” and bone functional adaptation.
                        <em>American Journal of Physical Anthropology, 165</em>(4), 687–704.
                    </li>
                    <li>
                        Schoenfeld, B. J. (2010).
                        The mechanisms of muscle hypertrophy and their application to resistance training.
                        <em>Journal of Strength and Conditioning Research, 24</em>(10), 2857–2872.
                    </li>
                    <li>
                        Wang, Q., Alén, M., Chen, X., &amp; Suominen, H. (2003).
                        Anthropometry and body composition predict musculoskeletal strength in 75–80-year-old men.
                        <em>Age and Ageing, 32</em>(4), 368–374.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <style>
        /* Modal shell */
        .fp-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .fp-modal.fp-modal-open {
            display: flex;
        }

        .fp-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(3px);
        }

        .fp-modal-dialog {
            position: relative;
            max-width: 900px;
            max-height: 90vh;
            width: 100%;
            margin: 1rem;
            background: radial-gradient(circle at top left, rgba(80, 70, 140, 0.35), rgba(8, 8, 20, 0.98));
            border-radius: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.25rem 1.5rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.75);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform: translateY(10px);
            opacity: 0;
            transition: opacity 0.18s ease-out, transform 0.18s ease-out;
        }

        .fp-modal.fp-modal-open .fp-modal-dialog {
            opacity: 1;
            transform: translateY(0);
        }

        .fp-modal-close {
            position: absolute;
            top: 0.5rem;
            right: 0.75rem;
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            border: none;
            background: rgba(0, 0, 0, 0.4);
            color: #f5f5f5;
            font-size: 1.25rem;
            cursor: pointer;
            line-height: 1;
        }

        .fp-modal-header {
            padding-right: 2rem;
            margin-bottom: 0.5rem;
        }

        .fp-modal-header h2 {
            margin: 0 0 0.35rem;
            font-size: 1.3rem;
            color: #f7f7ff;
        }

        .fp-modal-subtitle {
            margin: 0;
            font-size: 0.9rem;
            color: #d2d2ea;
        }

        .fp-modal-body {
            margin-top: 0.75rem;
            padding-right: 0.5rem;
            overflow-y: auto;
            font-size: 0.9rem;
            color: #d8d8f0;
            line-height: 1.6;
        }

        .fp-modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .fp-modal-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .fp-modal-body::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 999px;
        }

        .fp-article-heading {
            margin-top: 0.9rem;
            margin-bottom: 0.25rem;
            font-size: 1rem;
            color: #f7f7ff;
        }

        .fp-modal-body p {
            margin: 0 0 0.55rem;
        }

        .fp-ref-list {
            padding-left: 1.1rem;
            margin: 0.25rem 0 0.75rem;
            font-size: 0.8rem;
            color: #c5c5dd;
        }

        .fp-ref-list li {
            margin-bottom: 0.35rem;
        }

        @media (max-width: 600px) {
            .fp-modal-dialog {
                padding: 1rem;
            }

            .fp-modal-header h2 {
                font-size: 1.1rem;
            }
        }
    </style>

    <script>
        (function () {
            const modal     = document.getElementById('fp-article-modal');
            const dialog    = modal ? modal.querySelector('.fp-modal-dialog') : null;
            const closeBtn  = modal ? modal.querySelector('.fp-modal-close') : null;

            // Open handler – attach to any element with [data-fp-open-article]
            document.addEventListener('click', function (e) {
                const target = e.target;
                if (!(target instanceof Element)) return;

                if (target.matches('[data-fp-open-article]')) {
                    e.preventDefault();
                    if (!modal) return;
                    modal.classList.add('fp-modal-open');
                    modal.setAttribute('aria-hidden', 'false');
                }

                // Close when clicking backdrop
                if (modal && target === modal.querySelector('.fp-modal-backdrop')) {
                    closeModal();
                }
            });

            function closeModal() {
                if (!modal) return;
                modal.classList.remove('fp-modal-open');
                modal.setAttribute('aria-hidden', 'true');
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    closeModal();
                });
            }

            // ESC key to close
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('fp-modal-open')) {
                    closeModal();
                }
            });
        })();
    </script>
    <?php
}
