<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>About Us - Jahan Journal</title>
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <style>
      :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --secondary: #f59e0b;
        --dark: #1e293b;
        --light: #f8fafc;
        --gray: #64748b;
        --gray-light: #e2e8f0;
        --transition: all 0.3s ease;
        --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        --radius: 12px;
      }

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: "Inter", sans-serif;
        color: var(--dark);
        background-color: var(--light);
        line-height: 1.6;
        overflow-x: hidden;
      }

      h1,
      h2,
      h3,
      h4,
      h5 {
        font-family: "Playfair Display", serif;
        font-weight: 700;
        margin-bottom: 1rem;
      }

      .container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.5rem;
      }

      /* Header Styles */
      header {
        background-color: rgba(255, 255, 255, 0.95);
        box-shadow: var(--shadow);
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        backdrop-filter: blur(10px);
      }

      .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.2rem 0;
      }

      .logo {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary);
        text-decoration: none;
      }

      .logo-icon {
        font-size: 2.2rem;
        color: var(--secondary);
      }

      /* Navigation */
      nav ul {
        display: flex;
        list-style: none;
        gap: 2rem;
      }

      nav ul li a {
        color: var(--dark);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
        position: relative;
        padding: 0.5rem 0;
      }

      nav ul li a:after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background-color: var(--primary);
        transition: var(--transition);
      }

      nav ul li a:hover {
        color: var(--primary);
      }

      nav ul li a:hover:after {
        width: 100%;
      }

      .nav-buttons {
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .btn {
        padding: 0.7rem 1.5rem;
        border-radius: var(--radius);
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: inline-block;
      }

      .btn-primary {
        background-color: var(--primary);
        color: white;
        border: none;
      }

      .btn-primary:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
      }

      .btn-outline {
        background-color: transparent;
        color: var(--primary);
        border: 2px solid var(--primary);
      }

      .btn-outline:hover {
        background-color: var(--primary);
        color: white;
      }

      /* Mobile Navigation */
      .menu-toggle {
        display: none;
        flex-direction: column;
        cursor: pointer;
        gap: 6px;
      }

      .menu-toggle span {
        height: 3px;
        width: 25px;
        background: var(--dark);
        border-radius: 2px;
        transition: var(--transition);
      }

      /* Page Header */
      .page-header {
        padding: 8rem 0 4rem;
        background: linear-gradient(
          135deg,
          rgba(37, 99, 235, 0.05) 0%,
          rgba(245, 158, 11, 0.05) 100%
        );
        text-align: center;
        margin-top: 80px;
      }

      .page-title {
        font-size: 3.5rem;
        color: var(--dark);
        margin-bottom: 1rem;
      }

      .breadcrumb {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        color: var(--gray);
      }

      .breadcrumb a {
        color: var(--primary);
        text-decoration: none;
      }

      /* About Content */
      .about-content {
        padding: 5rem 0;
      }

      .about-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
      }

      .about-image {
        position: relative;
      }

      .about-image img {
        width: 100%;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
      }

      .about-image::after {
        content: "";
        position: absolute;
        top: 20px;
        left: 20px;
        right: -20px;
        bottom: -20px;
        border: 3px solid var(--secondary);
        border-radius: var(--radius);
        z-index: -1;
      }

      .about-text h2 {
        font-size: 2.5rem;
        margin-bottom: 1.5rem;
      }

      .about-text p {
        margin-bottom: 1.5rem;
        color: var(--gray);
        font-size: 1.1rem;
      }

      /* Mission Section */
      .mission {
        padding: 5rem 0;
        background-color: var(--dark);
        color: white;
      }

      .mission-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
      }

      .mission-text h2 {
        color: white;
        font-size: 2.5rem;
        margin-bottom: 1.5rem;
      }

      .mission-text p {
        margin-bottom: 1.5rem;
        font-size: 1.1rem;
        opacity: 0.9;
      }

      .values {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-top: 2rem;
      }

      .value-item {
        background: rgba(255, 255, 255, 0.1);
        padding: 2rem;
        border-radius: var(--radius);
        text-align: center;
        transition: var(--transition);
      }

      .value-item:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.15);
      }

      .value-icon {
        font-size: 2.5rem;
        color: var(--secondary);
        margin-bottom: 1rem;
      }

      .value-title {
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
      }

      /* Team Section */
      .team {
        padding: 5rem 0;
      }

      .section-title {
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
        padding-bottom: 1rem;
      }

      .section-title:after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 3px;
        background-color: var(--secondary);
      }

      .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
      }

      .team-member {
        background: white;
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: var(--transition);
        text-align: center;
      }

      .team-member:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.15);
      }

      .team-image {
        height: 280px;
        width: 100%;
        object-fit: cover;
      }

      .team-info {
        padding: 1.5rem;
      }

      .team-name {
        font-size: 1.4rem;
        margin-bottom: 0.5rem;
      }

      .team-role {
        color: var(--primary);
        font-weight: 500;
        margin-bottom: 1rem;
      }

      .team-social {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 1rem;
      }

      .team-social a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: var(--gray-light);
        border-radius: 50%;
        color: var(--dark);
        text-decoration: none;
        transition: var(--transition);
      }

      .team-social a:hover {
        background: var(--primary);
        color: white;
      }

      /* Timeline Section */
      .timeline {
        padding: 5rem 0;
        background: linear-gradient(
          135deg,
          rgba(37, 99, 235, 0.05) 0%,
          rgba(245, 158, 11, 0.05) 100%
        );
      }

      .timeline-container {
        position: relative;
        max-width: 800px;
        margin: 0 auto;
      }

      .timeline-container::after {
        content: "";
        position: absolute;
        width: 4px;
        background-color: var(--primary);
        top: 0;
        bottom: 0;
        left: 50%;
        margin-left: -2px;
      }

      .timeline-item {
        padding: 10px 40px;
        position: relative;
        width: 50%;
        box-sizing: border-box;
      }

      .timeline-item:nth-child(odd) {
        left: 0;
      }

      .timeline-item:nth-child(even) {
        left: 50%;
      }

      .timeline-content {
        padding: 20px;
        background-color: white;
        position: relative;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
      }

      .timeline-date {
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
      }

      .timeline-title {
        font-size: 1.3rem;
        margin-bottom: 0.5rem;
      }

      .timeline-item::after {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        background-color: white;
        border: 4px solid var(--primary);
        border-radius: 50%;
        top: 50%;
        right: -13px;
        z-index: 1;
        transform: translateY(-50%);
      }

      .timeline-item:nth-child(even)::after {
        left: -13px;
      }

      /* Footer */
      footer {
        background-color: var(--dark);
        color: white;
        padding: 4rem 0 2rem;
      }

      .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 3rem;
        margin-bottom: 3rem;
      }

      .footer-logo {
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        margin-bottom: 1rem;
        display: inline-block;
      }

      .footer-description {
        margin-bottom: 1.5rem;
        opacity: 0.8;
      }

      .social-links {
        display: flex;
        gap: 1rem;
      }

      .social-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        color: white;
        text-decoration: none;
        transition: var(--transition);
      }

      .social-link:hover {
        background: var(--primary);
        transform: translateY(-3px);
      }

      .footer-heading {
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        color: white;
      }

      .footer-links {
        list-style: none;
      }

      .footer-links li {
        margin-bottom: 0.8rem;
      }

      .footer-links a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: var(--transition);
      }

      .footer-links a:hover {
        color: var(--secondary);
        padding-left: 5px;
      }

      .footer-bottom {
        text-align: center;
        padding-top: 2rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        opacity: 0.7;
      }

      /* Responsive Design */
      @media (max-width: 992px) {
        .about-grid,
        .mission-grid {
          grid-template-columns: 1fr;
          gap: 3rem;
        }

        .timeline-container::after {
          left: 31px;
        }

        .timeline-item {
          width: 100%;
          padding-left: 70px;
          padding-right: 25px;
        }

        .timeline-item:nth-child(even) {
          left: 0;
        }

        .timeline-item::after {
          left: 21px;
          right: auto;
        }
      }

      @media (max-width: 768px) {
        .menu-toggle {
          display: flex;
        }

        nav ul {
          display: none;
          flex-direction: column;
          position: absolute;
          top: 100%;
          left: 0;
          width: 100%;
          background: white;
          padding: 1rem 0;
          box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        nav ul.show {
          display: flex;
        }

        nav ul li {
          margin: 0;
          padding: 0.7rem 2rem;
        }

        .nav-buttons {
          display: none;
        }

        .page-title {
          font-size: 2.5rem;
        }

        .values {
          grid-template-columns: 1fr;
        }
      }

      /* Animations */
      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .animate {
        animation: fadeIn 0.8s ease forwards;
      }

      .delay-1 {
        animation-delay: 0.2s;
      }
      .delay-2 {
        animation-delay: 0.4s;
      }
      .delay-3 {
        animation-delay: 0.6s;
      }
    </style>
  </head>
  <body>
    <!-- Header -->
    <?php include("header.php"); ?>

    <!-- Page Header -->
    <section class="page-header">
      <div class="container">
        <h1 class="page-title">About Jahan Journal</h1>
        <div class="breadcrumb">
          <a href="Journal.html">Home</a>
          <span>/</span>
          <span>About Us</span>
        </div>
      </div>
    </section>

    <!-- About Content -->
    <section class="about-content">
      <div class="container">
        <div class="about-grid">
          <div class="about-image animate">
            <img src="Aghz.jpg" />
          </div>
          <div class="about-text animate delay-1">
            <h2>Our Story</h2>
            <p>
              Jahan Journal was founded in 2010 with a simple mission: to
              provide insightful analysis and research on global issues that
              matter. What started as a small academic publication has grown
              into a respected source of information for readers across the
              globe.
            </p>
            <p>
              Our team consists of experienced journalists, researchers, and
              subject matter experts who are passionate about delivering
              accurate, thought-provoking content to our readers. We believe in
              the power of information to transform societies and create a
              better world.
            </p>
            <p>
              Today, Jahan Journal reaches over 500,000 readers monthly across
              60 countries, with content translated into 5 languages. We've
              published more than 1,200 research papers and articles on topics
              ranging from technology and environment to economics and health.
            </p>
            <a href="#" class="btn btn-primary">Our Publications</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Timeline Section -->
    <section class="timeline">
      <div class="container">
        <h2 class="section-title">Our Journey</h2>

        <div class="timeline-container">
          <div class="timeline-item animate">
            <div class="timeline-content">
              <div class="timeline-date">2010</div>
              <h3 class="timeline-title">Foundation</h3>
              <p>
                Jahan Journal was founded by a group of academics and
                journalists passionate about global issues.
              </p>
            </div>
          </div>
          <div class="timeline-item animate delay-1">
            <div class="timeline-content">
              <div class="timeline-date">2013</div>
              <h3 class="timeline-title">First International Edition</h3>
              <p>
                Launched our first edition in Spanish, expanding our reach to
                Latin American readers.
              </p>
            </div>
          </div>
          <div class="timeline-item animate delay-2">
            <div class="timeline-content">
              <div class="timeline-date">2016</div>
              <h3 class="timeline-title">Research Division</h3>
              <p>
                Established our dedicated research division to produce original
                studies and analysis.
              </p>
            </div>
          </div>
          <div class="timeline-item animate delay-3">
            <div class="timeline-content">
              <div class="timeline-date">2020</div>
              <h3 class="timeline-title">Digital Transformation</h3>
              <p>
                Completely redesigned our digital platform and launched a mobile
                app for better accessibility.
              </p>
            </div>
          </div>
          <div class="timeline-item animate">
            <div class="timeline-content">
              <div class="timeline-date">2023</div>
              <h3 class="timeline-title">Global Expansion</h3>
              <p>
                Reached over 500,000 monthly readers and expanded our team to
                include contributors from 25 countries.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <?php include("footer.php"); ?>

    <script>
      // Mobile menu toggle
      document.addEventListener("DOMContentLoaded", function () {
        const menuToggle = document.getElementById("menuToggle");
        const navMenu = document.getElementById("navMenu");

        menuToggle.addEventListener("click", function () {
          navMenu.classList.toggle("show");
          menuToggle.classList.toggle("active");
        });

        // Animation on scroll
        const observerOptions = {
          root: null,
          rootMargin: "0px",
          threshold: 0.1,
        };

        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add("animate");
            }
          });
        }, observerOptions);

        document.querySelectorAll(".animate").forEach((el) => {
          observer.observe(el);
        });
      });
    </script>
  </body>
</html>
